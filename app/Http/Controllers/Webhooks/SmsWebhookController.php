<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Sms\Webhooks\InvalidWebhookPayloadException;
use App\Domain\Sms\Webhooks\InvalidWebhookSignatureException;
use App\Domain\Sms\Webhooks\SmsWebhookProcessor;
use App\Domain\Sms\Webhooks\SmsWebhookRegistry;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * POST /webhooks/sms/{provider} — point d'entrée GÉNÉRIQUE des webhooks de fournisseurs SMS (sans session, sans CSRF).
 * Fail-closed, dans l'ordre : fournisseur sans vérificateur → 404 ; secret non configuré ou signature/horodatage invalide → 401 ;
 * corps trop gros → 413 ; corps illisible → 400 ; événement déjà reçu → 200 sans effet ; sinon traitement.
 * L'événement est enregistré (déduplication) et traité dans UNE transaction : si le traitement échoue (500), l'enregistrement
 * est annulé et le fournisseur pourra rejouer l'événement.
 */
class SmsWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, SmsWebhookRegistry $registry, SmsWebhookProcessor $processor): JsonResponse
    {
        $verifier = $registry->verifier($provider);
        if ($verifier === null) {
            return response()->json(['error' => 'unknown_provider'], 404);
        }
        // Refus générique : ne révèle pas si c'est le secret absent ou la signature qui pose problème.
        if (! $verifier->isConfigured()) {
            Log::warning('Webhook SMS refusé : aucun secret configuré.', ['provider' => $provider]);

            return response()->json(['error' => 'unauthorized'], 401);
        }

        $max = (int) config('sms.webhooks.max_body_bytes');
        $body = $request->getContent();
        if ((int) $request->headers->get('Content-Length', '0') > $max || strlen($body) > $max) {
            return response()->json(['error' => 'payload_too_large'], 413);
        }

        $headers = array_map(fn ($v) => is_array($v) ? (string) ($v[0] ?? '') : (string) $v, $request->headers->all());
        try {
            $verifier->verify($body, $headers);
        } catch (InvalidWebhookSignatureException) {
            Log::warning('Webhook SMS refusé : signature invalide.', ['provider' => $provider]);

            return response()->json(['error' => 'unauthorized'], 401);
        }

        try {
            $event = $verifier->parse($body);
        } catch (InvalidWebhookPayloadException) {
            return response()->json(['error' => 'invalid_payload'], 400);
        }

        $outcome = DB::transaction(function () use ($provider, $event, $processor) {
            // Aucun contenu du corps n'est conservé : seulement l'identité de l'événement.
            $inserted = DB::table('webhook_events')->insertOrIgnore([
                'provider' => $provider,
                'event_id' => $event->eventId,
                'type' => $event->type->value,
                'received_at' => now(),
            ]);
            if ($inserted === 0) {
                return 'duplicate';
            }

            $outcome = $processor->process($provider, $event);
            DB::table('webhook_events')->where('provider', $provider)->where('event_id', $event->eventId)->update(['outcome' => $outcome]);

            return $outcome;
        });

        return response()->json(['status' => $outcome]);
    }
}
