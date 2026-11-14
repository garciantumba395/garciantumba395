<?php

namespace App\Domain\Consent;

use App\Models\Consent;
use App\Models\Contact;
use App\Models\Suppression;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ConsentService
{
    public function record(Contact $contact, string $source, ?string $evidence, CarbonInterface $obtainedAt, ?int $userId = null): Consent
    {
        $this->assertValid($source, $evidence, $obtainedAt);

        if (Suppression::active()->where('phone', $contact->phone)->exists()) {
            throw new InvalidArgumentException('Ce numéro est désinscrit : levez la désinscription avant d\'enregistrer un nouveau consentement.');
        }

        return Consent::create([
            'contact_id' => $contact->id,
            'source' => $source,
            'evidence' => $evidence ?: null,
            'obtained_at' => $obtainedAt,
            'recorded_by' => $userId,
        ]);
    }

    public function revoke(Consent $consent, ?string $reason = null, ?int $userId = null): void
    {
        if ($consent->revoked_at === null) {
            $consent->update(['revoked_at' => now(), 'revoked_by' => $userId, 'revoke_reason' => $reason]);
        }
    }

    /** Désinscrit un numéro (idempotent) et révoque les consentements des contacts correspondants. */
    public function optOut(string $phone, string $source = 'manual', ?string $reason = null, ?int $userId = null): Suppression
    {
        return DB::transaction(function () use ($phone, $source, $reason, $userId) {
            $existing = Suppression::active()->where('phone', $phone)->first();
            $suppression = $existing ?? Suppression::create([
                'phone' => $phone, 'source' => $source, 'reason' => $reason, 'created_by' => $userId,
            ]);

            Consent::active()->whereIn('contact_id', Contact::where('phone', $phone)->select('id'))
                ->update(['revoked_at' => now(), 'revoked_by' => $userId, 'revoke_reason' => 'Désinscription']);

            return $suppression;
        });
    }

    /**
     * Lève une désinscription. Les anciens consentements ont été révoqués à la désinscription :
     * un NOUVEAU consentement est requis avant tout envoi.
     */
    public function lift(Suppression $suppression, string $reason, ?int $userId = null): void
    {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Un motif est obligatoire pour lever une désinscription.');
        }
        if ($suppression->lifted_at === null) {
            $suppression->update(['lifted_at' => now(), 'lifted_by' => $userId, 'lift_reason' => $reason]);
        }
    }

    private function assertValid(string $source, ?string $evidence, CarbonInterface $obtainedAt): void
    {
        if (! array_key_exists($source, config('consent.sources'))) {
            throw new InvalidArgumentException('Source de consentement inconnue.');
        }
        if (config('consent.require_evidence') && trim((string) $evidence) === '') {
            throw new InvalidArgumentException('Une preuve (texte ou référence) est obligatoire.');
        }
        if ($obtainedAt->isFuture()) {
            throw new InvalidArgumentException('La date de consentement ne peut pas être dans le futur.');
        }
    }
}
