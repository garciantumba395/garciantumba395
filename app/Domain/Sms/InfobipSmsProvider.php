<?php

namespace App\Domain\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Adaptateur Infobip (décision D1, voir docs/PROVIDERS.md). FORMAT À VALIDER EN SANDBOX AVANT TOUTE ACTIVATION :
 * il a été écrit sans accès à la documentation officielle (hôte non joignable depuis l'environnement de développement).
 * Jamais de nouvel essai automatique : toute incertitude lève une exception (résultat inconnu, pas de remboursement).
 */
final class InfobipSmsProvider implements SmsProvider
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $apiKey,
        private readonly ?string $defaultSender = null,
        private readonly int $timeoutSeconds = 15,
    ) {}

    public function name(): string
    {
        return 'infobip';
    }

    public function isLive(): bool
    {
        return true;
    }

    public function send(SmsRequest $request): SmsResult
    {
        if ($this->baseUrl === '' || $this->apiKey === '') {
            throw new RuntimeException('Infobip non configuré (INFOBIP_BASE_URL / INFOBIP_API_KEY).');
        }

        $message = [
            'messageId' => $request->reference, // référence plateforme, unique par message
            'destinations' => [['to' => ltrim($request->to, '+')]],
            'content' => ['text' => $request->body],
        ];
        if (($sender = $request->senderId ?? $this->defaultSender) !== null && $sender !== '') {
            $message['sender'] = $sender;
        }

        $response = Http::withHeaders(['Authorization' => 'App '.$this->apiKey, 'Accept' => 'application/json'])
            ->timeout($this->timeoutSeconds)
            ->withOptions(['allow_redirects' => false])
            ->asJson()
            ->post('https://'.$this->baseUrl.'/sms/3/messages', ['messages' => [$message]]);

        // 400/422 : requête refusée avant tout envoi (numéro ou contenu invalide) = refus définitif.
        if (in_array($response->status(), [400, 422], true)) {
            return SmsResult::rejected('infobip_'.$response->status(), 'Requête refusée par le fournisseur.');
        }

        // Tout le reste hors 2xx (401/403 : configuration, 429, 5xx) : résultat inconnu, on ne rembourse ni ne renvoie.
        if (! $response->successful()) {
            throw new RuntimeException('Infobip : réponse HTTP '.$response->status());
        }

        $item = $response->json('messages.0');
        $id = is_array($item) ? ($item['messageId'] ?? null) : null;
        $group = is_array($item) ? strtoupper((string) ($item['status']['groupName'] ?? '')) : '';

        if (! is_string($id) || $id === '' || strlen($id) > 100) {
            throw new RuntimeException('Infobip : réponse illisible.');
        }

        if ($group === 'REJECTED') {
            return SmsResult::rejected('infobip_rejected', 'Message rejeté par le fournisseur.');
        }

        if (in_array($group, ['PENDING', 'DELIVERED'], true)) {
            return SmsResult::accepted($id);
        }

        throw new RuntimeException('Infobip : statut inattendu.'); // inconnu : réconciliation manuelle
    }
}
