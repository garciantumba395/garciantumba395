<?php

namespace App\Domain\Audit;

use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Écrit dans le journal d'audit (ajout seul). RÈGLE : jamais de secret, de mot de passe, de contenu de message
 * ni de numéro de téléphone dans les détails — uniquement des identifiants et des valeurs techniques.
 * Deux garde-fous : une clé de détail suspecte est REFUSÉE (erreur de programmation) ; une valeur texte qui ressemble
 * à un numéro de téléphone ou à une adresse e-mail est MASQUÉE (ex. motif saisi librement).
 */
class AuditLogger
{
    /** Fragments interdits dans les NOMS de clés des détails. */
    private const FORBIDDEN_KEYS = ['password', 'passwd', 'secret', 'token', 'body', 'message', 'content', 'phone', 'msisdn', 'mobile', 'email', 'api_key', 'apikey', 'authorization', 'number'];

    public const MASK = '[masqué]';

    /** Action de l'utilisateur connecté (acteur = Auth::id()). */
    public function record(string $action, Company|int|null $company = null, ?Model $subject = null, array $details = []): AuditLog
    {
        return $this->write($action, Auth::id(), $company, $subject, $details);
    }

    /** Action du système (jobs, planificateur, console) : acteur vide. */
    public function recordSystem(string $action, Company|int|null $company = null, ?Model $subject = null, array $details = []): AuditLog
    {
        return $this->write($action, null, $company, $subject, $details);
    }

    /** Action d'un acteur précisé (ex. commande console). */
    public function recordAs(?int $actorId, string $action, Company|int|null $company = null, ?Model $subject = null, array $details = []): AuditLog
    {
        return $this->write($action, $actorId, $company, $subject, $details);
    }

    private function write(string $action, ?int $actorId, Company|int|null $company, ?Model $subject, array $details): AuditLog
    {
        if (! preg_match('/^[a-z0-9_]+(\.[a-z0-9_]+)+$/', $action)) {
            throw new InvalidArgumentException('Clé d\'action invalide.');
        }

        $request = app()->runningInConsole() && ! app()->runningUnitTests() ? null : request();

        return AuditLog::create([
            'actor_id' => $actorId,
            'company_id' => $company instanceof Company ? $company->id : $company,
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'details' => $details === [] ? null : $this->sanitize($details),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent() ? Str::limit($request->userAgent(), 255, '') : null,
        ]);
    }

    private function sanitize(array $details): array
    {
        $clean = [];
        foreach ($details as $key => $value) {
            if (is_string($key)) {
                foreach (self::FORBIDDEN_KEYS as $bad) {
                    if (str_contains(strtolower($key), $bad)) {
                        throw new InvalidArgumentException("Clé de détail interdite dans le journal d'audit : $key");
                    }
                }
            }
            $clean[$key] = match (true) {
                is_array($value) => $this->sanitize($value),
                is_string($value) => $this->maskString($value),
                is_int($value), is_float($value), is_bool($value), $value === null => $value,
                $value instanceof \BackedEnum => $value->value,
                default => throw new InvalidArgumentException('Valeur de détail non supportée dans le journal d\'audit.'),
            };
        }

        return $clean;
    }

    private function maskString(string $value): string
    {
        $value = preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/', self::MASK, $value);
        $value = preg_replace('/\+?\d[\d\s().\-]{6,}\d/', self::MASK, $value);

        return Str::limit($value, 255, '');
    }
}
