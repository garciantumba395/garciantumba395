<?php

namespace App\Domain\Sms;

use Closure;

/**
 * Résout le fournisseur actif. Verrou d'activation : un fournisseur « live » n'est JAMAIS instancié
 * tant que sms.sending_enabled n'est pas explicitement à true (défense en profondeur, en plus du répartiteur).
 */
class SmsManager
{
    /** @var array<string,Closure():SmsProvider> */
    private array $drivers = [];

    /** @var array<string,SmsProvider> */
    private array $resolved = [];

    public function __construct()
    {
        $this->drivers['fake'] = fn () => new FakeSmsProvider;
    }

    /** Enregistre un fournisseur (futur : agrégateur réel). */
    public function extend(string $name, Closure $factory): void
    {
        $this->drivers[$name] = $factory;
        unset($this->resolved[$name]);
    }

    public function provider(?string $name = null): SmsProvider
    {
        $name ??= (string) config('sms.provider', 'fake');

        if (! isset($this->drivers[$name])) {
            throw new UnknownSmsProviderException("Fournisseur SMS inconnu : {$name}");
        }

        $provider = $this->resolved[$name] ??= ($this->drivers[$name])();

        if ($provider->isLive() && ! config('sms.sending_enabled')) {
            unset($this->resolved[$name]);
            throw new SmsSendingDisabledException(
                "Le fournisseur « {$name} » enverrait de vrais SMS : activez-le explicitement (SMS_SENDING_ENABLED=true)."
            );
        }

        return $provider;
    }

    /** true si l'envoi atteindrait de vraies personnes avec la configuration actuelle. */
    public function isLiveMode(): bool
    {
        try {
            return $this->provider()->isLive();
        } catch (SmsSendingDisabledException) {
            return false; // verrou actif : rien ne part
        }
    }
}
