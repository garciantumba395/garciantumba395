<?php

namespace App\Domain\Legal;

use App\Models\Contact;
use App\Models\UnsubscribeLink;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Un lien stable par (entreprise, numéro) : le même destinataire reçoit toujours le même lien. Création sûre en concurrence (INSERT … ON CONFLICT DO NOTHING). */
class UnsubscribeLinks
{
    public function forContact(Contact $contact): UnsubscribeLink
    {
        $key = ['company_id' => $contact->company_id, 'phone' => $contact->phone];

        for ($i = 0; $i < 5; $i++) {
            if ($existing = UnsubscribeLink::where($key)->first()) {
                return $existing;
            }
            DB::table('unsubscribe_links')->insertOrIgnore($key + ['code' => Str::random((int) config('legal.unsubscribe.code_length')), 'created_at' => now()]);
        }

        throw new RuntimeException('Impossible de créer le lien de désabonnement.');
    }

    public function url(string $code): string
    {
        return route('unsubscribe.show', ['code' => $code]);
    }

    /** URL de même longueur qu'un vrai lien, sans écriture en base (estimations de segments et de coût). */
    public function placeholderUrl(): string
    {
        return $this->url(str_repeat('x', (int) config('legal.unsubscribe.code_length')));
    }
}
