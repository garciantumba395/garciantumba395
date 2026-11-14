<?php

namespace App\Domain\Sms;

/**
 * Contrat d'un fournisseur SMS. Pour en brancher un nouveau : implémenter cette interface
 * puis l'enregistrer dans SmsManager::extend(). Aucun autre code n'en dépend.
 */
interface SmsProvider
{
    /** Identifiant stable stocké dans le journal (ex. "fake"). */
    public function name(): string;

    /** true si l'envoi atteint un réseau réel (coûts, messages reçus par de vraies personnes). */
    public function isLive(): bool;

    /**
     * @throws \Throwable en cas d'erreur de transport : le résultat est alors INCONNU
     *                    (le message a pu partir) et ne doit jamais être renvoyé automatiquement.
     */
    public function send(SmsRequest $request): SmsResult;
}
