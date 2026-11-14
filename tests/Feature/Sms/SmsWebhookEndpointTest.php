<?php

namespace Tests\Feature\Sms;

use App\Domain\Sms\SmsStatus;
use App\Domain\Sms\Webhooks\FakeSmsWebhookVerifier;
use App\Domain\Sms\Webhooks\SmsWebhookEvent;
use App\Domain\Sms\Webhooks\SmsWebhookProcessor;
use App\Domain\Sms\Webhooks\SmsWebhookRegistry;
use App\Domain\Sms\Webhooks\SmsWebhookVerifier;
use App\Models\Company;
use App\Models\User;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\SignsFakeWebhooks;
use Tests\TestCase;

class SmsWebhookEndpointTest extends TestCase
{
    use RefreshDatabase;
    use SignsFakeWebhooks;

    private Company $a;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
        $this->configureWebhookSecret();
        $this->a = Company::createFor(User::factory()->create(), 'A');
    }

    protected function tearDown(): void
    {
        Http::assertNothingSent(); // aucun appel réseau, jamais
        parent::tearDown();
    }

    private function report(string $eventId = 'evt-1', string $messageId = 'fake-1', string $status = 'delivered', ?string $error = null): array
    {
        return ['event_id' => $eventId, 'type' => 'delivery_report', 'message_id' => $messageId, 'status' => $status, 'error_code' => $error];
    }

    // --- Fail-closed : secret, fournisseur, signature ---

    public function test_empty_secret_refuses_everything_even_a_signature_computed_with_the_empty_key(): void
    {
        config(['sms.webhooks.fake_secret' => '']);
        $this->makeMessage($this->a, '+243810000001', 'sent', false, 'fake-1');
        $body = json_encode($this->report());

        $this->postWebhook($body, $this->fakeHeaders($body, null, ''))->assertStatus(401);
        $this->postWebhook($body, $this->fakeHeaders($body, null, $this->webhookSecret))->assertStatus(401);
        $this->postWebhook($body, [])->assertStatus(401);

        $this->assertSame('sent', DB::table('sms_messages')->value('status'));
        $this->assertSame(0, DB::table('webhook_events')->count());
    }

    public function test_default_configuration_has_no_secret(): void
    {
        // Valeur par défaut du fichier de configuration et du .env.example : vide (aucun secret dans le dépôt).
        $this->assertSame('', (require config_path('sms.php'))['webhooks']['fake_secret']);
        $this->assertMatchesRegularExpression('/^SMS_WEBHOOK_FAKE_SECRET=$/m', file_get_contents(base_path('.env.example')));
    }

    public function test_unknown_provider_is_refused_with_404(): void
    {
        $body = json_encode($this->report());
        $this->postWebhook($body, null, 'inconnu')->assertStatus(404)->assertJson(['error' => 'unknown_provider']);
    }

    public function test_provider_registered_without_secret_is_refused_with_401(): void
    {
        $this->app->make(SmsWebhookRegistry::class)->extend('sans-secret', fn () => new class implements SmsWebhookVerifier
        {
            public function isConfigured(): bool
            {
                return false;
            }

            public function verify(string $rawBody, array $headers): void
            {
                // ne doit jamais être appelé : le contrôleur refuse avant
                throw new RuntimeException('verify() appelé alors que le secret est absent');
            }

            public function parse(string $rawBody): SmsWebhookEvent
            {
                throw new RuntimeException('parse() ne doit pas être appelé');
            }
        });

        $this->postWebhook('{}', [], 'sans-secret')->assertStatus(401);
    }

    public function test_valid_signature_is_accepted_and_response_is_json(): void
    {
        $this->makeMessage($this->a, '+243810000001', 'sent', false, 'fake-1');

        $response = $this->postWebhook($this->report());

        $response->assertOk()->assertJson(['status' => 'applied']);
        $this->assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertSame('delivered', DB::table('sms_messages')->value('status'));
    }

    public function test_missing_invalid_wrong_secret_and_tampered_signatures_are_refused(): void
    {
        $this->makeMessage($this->a, '+243810000001', 'sent', false, 'fake-1');
        $body = json_encode($this->report());
        $good = $this->fakeHeaders($body);

        $this->postWebhook($body, [])->assertStatus(401);                                              // absente
        $this->postWebhook($body, ['X-Fake-Timestamp' => (string) time()])->assertStatus(401);         // sans signature
        $this->postWebhook($body, ['X-Fake-Signature' => $good['X-Fake-Signature']])->assertStatus(401); // sans horodatage
        $this->postWebhook($body, [...$good, 'X-Fake-Signature' => str_repeat('0', 64)])->assertStatus(401); // invalide
        $this->postWebhook($body, $this->fakeHeaders($body, null, 'autre-secret'))->assertStatus(401);  // mauvais secret
        $this->postWebhook(str_replace('fake-1', 'fake-2', $body), $good)->assertStatus(401);           // corps modifié
        $this->postWebhook($body, [...$good, 'X-Fake-Timestamp' => (string) (time() + 1)])->assertStatus(401); // horodatage non signé

        $this->assertSame('sent', DB::table('sms_messages')->value('status'));
        $this->assertSame(0, DB::table('webhook_events')->count());
    }

    // --- Horodatage ---

    public function test_stale_future_and_malformed_timestamps_are_refused_but_the_window_edge_is_accepted(): void
    {
        $this->makeMessage($this->a, '+243810000001', 'sent', false, 'fake-1');
        $body = json_encode($this->report());
        $tolerance = (int) config('sms.webhooks.tolerance_seconds');

        $this->postWebhook($body, $this->fakeHeaders($body, time() - $tolerance - 5))->assertStatus(401);
        $this->postWebhook($body, $this->fakeHeaders($body, time() + $tolerance + 5))->assertStatus(401);
        $this->postWebhook($body, [...$this->fakeHeaders($body), 'X-Fake-Timestamp' => 'abc'])->assertStatus(401);
        $this->assertSame(0, DB::table('webhook_events')->count());

        $this->postWebhook($body, $this->fakeHeaders($body, time() - $tolerance + 5))->assertOk();
    }

    public function test_tolerance_is_configurable(): void
    {
        config(['sms.webhooks.tolerance_seconds' => 10]);
        $body = json_encode($this->report());

        $this->postWebhook($body, $this->fakeHeaders($body, time() - 60))->assertStatus(401);
    }

    // --- Déduplication / rejeu ---

    public function test_replayed_event_is_acknowledged_without_effect(): void
    {
        $message = $this->makeMessage($this->a, '+243810000001', 'sent', false, 'fake-1');

        $this->postWebhook($this->report())->assertOk()->assertJson(['status' => 'applied']);
        $this->assertSame('delivered', $message->fresh()->status->value);

        // On remet le message en « sent » : un rejeu de l'événement ne doit PAS le ré-appliquer.
        DB::table('sms_messages')->where('id', $message->id)->update(['status' => 'sent']);
        $this->postWebhook($this->report())->assertOk()->assertJson(['status' => 'duplicate']);

        $this->assertSame('sent', $message->fresh()->status->value);
        $this->assertSame(1, DB::table('webhook_events')->where('provider', 'fake')->where('event_id', 'evt-1')->count());
    }

    public function test_unique_constraint_on_provider_and_event_id(): void
    {
        $row = ['provider' => 'fake', 'event_id' => 'evt-x', 'type' => 'inbound', 'received_at' => now()];
        DB::table('webhook_events')->insert($row);
        DB::table('webhook_events')->insert([...$row, 'provider' => 'autre']); // même event_id, autre fournisseur : permis

        $this->expectException(QueryException::class);
        DB::table('webhook_events')->insert($row);
    }

    public function test_failed_processing_rolls_back_the_dedup_record_so_the_provider_can_retry(): void
    {
        $this->mock(SmsWebhookProcessor::class, fn ($m) => $m->shouldReceive('process')->andThrow(new RuntimeException('panne simulée')));

        $this->postWebhook($this->report())->assertStatus(500);

        $this->assertSame(0, DB::table('webhook_events')->count());
    }

    // --- Taille, forme du corps ---

    public function test_oversized_body_is_refused_with_413_before_any_effect(): void
    {
        config(['sms.webhooks.max_body_bytes' => 200]);
        $big = json_encode($this->report('evt-big', 'fake-1', 'delivered', null) + ['padding' => str_repeat('x', 500)]);

        $this->postWebhook($big)->assertStatus(413);          // correctement signé
        $this->postWebhook($big, [])->assertStatus(413);      // non signé : refusé sur la taille d'abord
        $this->assertSame(0, DB::table('webhook_events')->count());
    }

    public function test_authenticated_but_malformed_payloads_get_400_and_are_not_recorded(): void
    {
        $this->postWebhook('pas du json')->assertStatus(400);
        $this->postWebhook(['event_id' => 'e', 'type' => 'inconnu'])->assertStatus(400);
        $this->postWebhook(['type' => 'inbound', 'from' => '+243810000001', 'text' => 'STOP'])->assertStatus(400);           // sans event_id
        $this->postWebhook($this->report('e2', 'fake-1', 'sent'))->assertStatus(400);                                            // statut non final
        $this->postWebhook(['event_id' => 'e3', 'type' => 'inbound', 'from' => '0810000001', 'text' => 'STOP'])->assertStatus(400); // pas E.164

        $this->assertSame(0, DB::table('webhook_events')->count());
    }

    // --- Accusés de réception ---

    public function test_delivery_report_progresses_status_and_never_goes_backwards(): void
    {
        $sent = $this->makeMessage($this->a, '+243810000001', 'sent', false, 'fake-1');
        $unknown = $this->makeMessage($this->a, '+243810000002', 'unknown', false, 'fake-2');
        $delivered = $this->makeMessage($this->a, '+243810000003', 'delivered', false, 'fake-3');
        $failed = $this->makeMessage($this->a, '+243810000004', 'failed', false, 'fake-4');

        $this->postWebhook($this->report('e1', 'fake-1', 'delivered'))->assertJson(['status' => 'applied']);
        $this->postWebhook($this->report('e2', 'fake-2', 'failed', 'ERR42'))->assertJson(['status' => 'applied']);
        $this->postWebhook($this->report('e3', 'fake-3', 'failed', 'LATE'));   // accusé tardif contradictoire
        $this->postWebhook($this->report('e4', 'fake-4', 'delivered'));        // idem

        $this->assertSame(SmsStatus::Delivered, $sent->fresh()->status);
        $this->assertNotNull($sent->fresh()->delivered_at);
        $this->assertSame(SmsStatus::Failed, $unknown->fresh()->status);
        $this->assertSame('ERR42', $unknown->fresh()->error_code);
        $this->assertSame(SmsStatus::Delivered, $delivered->fresh()->status);   // pas de recul
        $this->assertNull($delivered->fresh()->error_code);
        $this->assertSame(SmsStatus::Failed, $failed->fresh()->status);         // pas de recul
    }

    public function test_report_for_unknown_message_or_other_provider_is_ignored(): void
    {
        $other = $this->makeMessage($this->a, '+243810000001', 'sent', false, 'fake-1', provider: 'autre');

        $this->postWebhook($this->report('e1', 'fake-1'))->assertOk()->assertJson(['status' => 'ignored']);
        $this->postWebhook($this->report('e2', 'inexistant'))->assertOk()->assertJson(['status' => 'ignored']);

        $this->assertSame(SmsStatus::Sent, $other->fresh()->status);
        $this->assertSame(2, DB::table('webhook_events')->count()); // acquittés : le fournisseur ne rejoue pas
    }

    public function test_report_is_applied_to_the_provider_named_in_the_url_only(): void
    {
        // Second fournisseur de test réutilisant le format fictif : même identifiant de message chez deux fournisseurs.
        $this->app->make(SmsWebhookRegistry::class)->extend('autre', fn () => new FakeSmsWebhookVerifier);
        $fake = $this->makeMessage($this->a, '+243810000001', 'sent', false, 'id-1', provider: 'fake');
        $autre = $this->makeMessage($this->a, '+243810000002', 'sent', false, 'id-1', provider: 'autre');

        $this->postWebhook($this->report('e1', 'id-1'), null, 'autre')->assertJson(['status' => 'applied']);

        $this->assertSame(SmsStatus::Delivered, $autre->fresh()->status);
        $this->assertSame(SmsStatus::Sent, $fake->fresh()->status);
    }

    // --- Hors session / CSRF / débit ---

    public function test_route_has_no_session_cookie_or_csrf_middleware(): void
    {
        $route = app('router')->getRoutes()->getByName('webhooks.sms');
        $middleware = app('router')->gatherRouteMiddleware($route);

        foreach ([PreventRequestForgery::class, StartSession::class, EncryptCookies::class, 'auth', 'company'] as $forbidden) {
            $this->assertNotContains($forbidden, $middleware);
        }
        $this->assertCount(1, array_filter($middleware, fn ($m) => is_string($m) && str_ends_with($m, ':sms-webhooks')));

        $response = $this->postWebhook($this->report());
        $this->assertCount(0, $response->headers->getCookies());
    }

    public function test_endpoint_is_rate_limited(): void
    {
        config(['sms.webhooks.rate_limit_per_minute' => 3]);
        $body = json_encode($this->report());

        for ($i = 0; $i < 3; $i++) {
            $this->postWebhook($body, [])->assertStatus(401); // même les requêtes refusées comptent
        }
        $this->postWebhook($body, [])->assertStatus(429);
        $this->postWebhook($body)->assertStatus(429); // une requête valide est aussi limitée
    }
}
