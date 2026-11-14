<?php

namespace App\Domain\Contacts;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\ContactImport;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Import CSV de contacts pour l'entreprise active.
 * - idempotent : réimporter le même fichier ne crée aucun doublon (clé = numéro E.164) ;
 * - le fichier n'est pas conservé, seul un rapport (compteurs + lignes en erreur sans données personnelles) l'est ;
 * - ne gère PAS le consentement (étape 3) : les contacts importés n'ont aucun droit d'envoi à ce stade.
 */
class ContactImporter
{
    public const MAX_ROWS = 10000;

    public const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_REPORTED_ERRORS = 100;

    private const BATCH = 500;

    private const ALIASES = [
        'phone' => ['telephone', 'phone', 'numero', 'tel', 'mobile', 'gsm', 'numero de telephone'],
        'name' => ['nom', 'name', 'nom complet', 'full name', 'fullname'],
        'email' => ['email', 'e-mail', 'mail', 'courriel'],
    ];

    public function __construct(private PhoneNormalizer $phones, private CurrentCompany $current) {}

    public function import(string $path, string $originalName, ?int $groupId = null, ?int $userId = null): ContactImport
    {
        $company = $this->current->company() ?? throw new InvalidArgumentException('Aucune entreprise active.');

        if (! is_file($path) || filesize($path) > self::MAX_BYTES) {
            throw new InvalidArgumentException('Fichier introuvable ou trop volumineux (5 Mo maximum).');
        }

        // Le groupe est cherché via le scope : un ID d'une autre entreprise est introuvable.
        $group = $groupId ? ContactGroup::find($groupId) : null;
        if ($groupId && ! $group) {
            throw new InvalidArgumentException('Groupe introuvable.');
        }

        $text = $this->readUtf8($path);
        $lines = preg_split('/\r\n|\n|\r/', $text);
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));
        if (count($lines) < 2) {
            throw new InvalidArgumentException('Le fichier doit contenir une ligne d\'en-tête et au moins une ligne de données.');
        }
        if (count($lines) - 1 > self::MAX_ROWS) {
            throw new InvalidArgumentException('Trop de lignes (maximum '.self::MAX_ROWS.' par import).');
        }

        $delimiter = $this->detectDelimiter($lines[0]);
        $columns = $this->mapHeader(str_getcsv(array_shift($lines), $delimiter, '"', ''));
        if (! isset($columns['phone'])) {
            throw new InvalidArgumentException('Colonne du numéro de téléphone introuvable (attendu : telephone, phone, numero, tel, mobile…).');
        }

        $stats = ['total' => 0, 'created' => 0, 'updated' => 0, 'invalid' => 0, 'duplicate' => 0];
        $errors = [];
        $seen = [];
        $valid = [];

        foreach ($lines as $i => $line) {
            $lineNo = $i + 2; // en-tête = ligne 1
            $stats['total']++;
            $cells = str_getcsv($line, $delimiter, '"', '');
            $phone = $this->phones->normalize($cells[$columns['phone']] ?? '', $company->country);
            if ($phone === null) {
                $this->fail($stats, $errors, $lineNo, 'Numéro invalide');

                continue;
            }
            $name = isset($columns['name']) ? trim($cells[$columns['name']] ?? '') : '';
            $email = isset($columns['email']) ? strtolower(trim($cells[$columns['email']] ?? '')) : '';
            if ($email !== '' && Validator::make(['e' => $email], ['e' => 'email|max:255'])->fails()) {
                $this->fail($stats, $errors, $lineNo, 'E-mail invalide');

                continue;
            }
            if (isset($seen[$phone])) {
                $stats['duplicate']++;
                if (count($errors) < self::MAX_REPORTED_ERRORS) {
                    $errors[] = ['line' => $lineNo, 'reason' => 'Doublon dans le fichier'];
                }

                continue;
            }
            $seen[$phone] = true;
            $valid[] = ['phone' => $phone, 'name' => mb_substr($name, 0, 255) ?: null, 'email' => $email ?: null];
        }

        return DB::transaction(function () use ($valid, $group, $originalName, $userId, $stats, $errors, $company) {
            foreach (array_chunk($valid, self::BATCH) as $batch) {
                $this->upsertBatch($batch, $company->id, $group, $stats);
            }

            return ContactImport::create([
                'user_id' => $userId,
                'contact_group_id' => $group?->id,
                'filename' => mb_substr(basename($originalName), 0, 255),
                'total_rows' => $stats['total'],
                'created_count' => $stats['created'],
                'updated_count' => $stats['updated'],
                'invalid_count' => $stats['invalid'],
                'duplicate_count' => $stats['duplicate'],
                'errors' => $errors ?: null,
            ]);
        });
    }

    private function upsertBatch(array $batch, int $companyId, ?ContactGroup $group, array &$stats): void
    {
        $existing = Contact::whereIn('phone', array_column($batch, 'phone'))->get()->keyBy('phone');

        $new = [];
        foreach ($batch as $row) {
            if ($c = $existing->get($row['phone'])) {
                $changes = array_filter(
                    ['name' => $row['name'], 'email' => $row['email']],
                    fn ($v, $k) => $v !== null && $v !== $c->{$k}, ARRAY_FILTER_USE_BOTH
                );
                if ($changes) {
                    $c->update($changes);
                    $stats['updated']++;
                }
            } else {
                $new[] = $row + ['company_id' => $companyId, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        if ($new) {
            $stats['created'] += Contact::query()->insertOrIgnore($new);
        }
        if ($group) {
            $group->addContacts(Contact::whereIn('phone', array_column($batch, 'phone'))->pluck('id')->all());
        }
    }

    private function fail(array &$stats, array &$errors, int $line, string $reason): void
    {
        $stats['invalid']++;
        if (count($errors) < self::MAX_REPORTED_ERRORS) {
            $errors[] = ['line' => $line, 'reason' => $reason];
        }
    }

    private function readUtf8(string $path): string
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        if (! mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'Windows-1252'); // export Excel classique
        }

        return $text;
    }

    private function detectDelimiter(string $header): string
    {
        $best = ',';
        $max = 0;
        foreach ([',', ';', "\t"] as $d) {
            $n = substr_count($header, $d);
            if ($n > $max) {
                [$best, $max] = [$d, $n];
            }
        }

        return $best;
    }

    /** @return array<string,int> champ => index de colonne */
    private function mapHeader(array $header): array
    {
        $map = [];
        foreach ($header as $index => $label) {
            $key = $this->slug((string) $label);
            foreach (self::ALIASES as $field => $aliases) {
                if (! isset($map[$field]) && in_array($key, $aliases, true)) {
                    $map[$field] = $index;
                }
            }
        }

        return $map;
    }

    private function slug(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'ô' => 'o', 'î' => 'i', 'ç' => 'c', 'ù' => 'u']);

        return preg_replace('/[\s_]+/', ' ', $s);
    }
}
