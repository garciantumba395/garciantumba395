<?php

namespace Tests\Contracts;

use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Domain\Sms\SmsDispatcher;
use App\Domain\Sms\SmsManager;
use App\Domain\Sms\SmsProvider;
use App\Domain\Sms\SmsRequest;
use App\Domain\Sms\SmsResult;
use App\Domain\Sms\SmsSendingDisabledException;
use App\Domain\Sms\SmsStatus;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Throwable;

/**
 * CONTRAT DE TEST d'un adaptateur SmsProvider, réutilisable par TOUT fournisseur (fictif ou réel, en sandbox/simulé).
 * Pour tester un nouvel adaptateur : créer une classe concrète qui étend celle-ci et fournit les fabriques ci-dessous
 * (voir docs/PROVIDERS.md). Aucun appel réseau réel : un adaptateur réel doit être testé avec Http::fake().
 */
abstract class SmsProviderContractTest extends TestCase
{
    use RefreshDatabase;

    private const DISPATCH_PHONE = '+243812345678';

    /** Une instance NEUVE de l'adaptateur. */
    abstract protected function makeProvider(): SmsProvider;

    /** Requête que le fournisseur ACCEPTE (numéro de dispatch fictif +243812345678 par défaut). */
    protected function acceptedRequest(): SmsRequest
    {
        return new SmsRequest(self::DISPATCH_PHONE, 'Bonjour (test)', 'contract-ref-1');
    }

    /** Requête que le fournisseur REFUSE définitivement ; null si l'adaptateur ne sait pas la simuler. */
    abstract protected function rejectedRequest(): ?SmsRequest;

    /** Requête provoquant une erreur de transport (le résultat est alors inconnu) ; null si non simulable. */
    abstract protected function transportErrorRequest(): ?SmsRequest;

    /** true si deux envois de la MÊME référence donnent le même identifiant fournisseur (idempotence côté fournisseur). */
    protected function referenceIsIdempotent(): bool
    {
        return false;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Http::fake();
    }

    public function test_name_is_stable_and_fits_the_journal_and_the_webhook_route(): void
    {
        $name = $this->makeProvider()->name();

        $this->assertMatchesRegularExpression('/^[a-z0-9_-]{1,30}$/', $name); // colonne provider(30) + route /webhooks/sms/{provider}
        $this->assertSame($name, $this->makeProvider()->name());
        $provider = $this->makeProvider();
        $this->assertSame($provider->name(), $provider->name());
    }

    public function test_is_live_is_a_stable_boolean(): void
    {
        $provider = $this->makeProvider();

        $this->assertIsBool($provider->isLive());
        $this->assertSame($provider->isLive(), $provider->isLive());
        $this->assertSame($provider->isLive(), $this->makeProvider()->isLive());
    }

    public function test_accepted_send_returns_an_accepted_result_with_a_provider_message_id(): void
    {
        $result = $this->makeProvider()->send($this->acceptedRequest());

        $this->assertInstanceOf(SmsResult::class, $result);
        $this->assertTrue($result->accepted);
        $this->assertIsString($result->providerMessageId);
        $this->assertNotSame('', $result->providerMessageId);
        $this->assertLessThanOrEqual(100, strlen($result->providerMessageId)); // colonne provider_message_id(100)
        $this->assertNull($result->errorCode);
        $this->assertNull($result->errorMessage);
    }

    public function test_definitive_refusal_is_a_result_not_an_exception(): void
    {
        $request = $this->rejectedRequest();
        if ($request === null) {
            $this->markTestSkipped('Refus définitif non simulable par cet adaptateur.');
        }

        $result = $this->makeProvider()->send($request);

        $this->assertFalse($result->accepted);
        $this->assertNull($result->providerMessageId);
        $this->assertNotEmpty($result->errorCode);
        $this->assertLessThanOrEqual(50, strlen((string) $result->errorCode));   // colonne error_code(50)
        $this->assertNotEmpty($result->errorMessage);
    }

    public function test_transport_error_is_thrown_never_swallowed_into_a_result(): void
    {
        $request = $this->transportErrorRequest();
        if ($request === null) {
            $this->markTestSkipped('Erreur de transport non simulable par cet adaptateur.');
        }

        // Une erreur de transport doit LEVER : le répartiteur marque alors « unknown » sans rembourser ni renvoyer.
        $this->expectException(Throwable::class);
        $this->makeProvider()->send($request);
    }

    public function test_sending_the_same_reference_twice_does_not_throw(): void
    {
        $provider = $this->makeProvider();

        $first = $provider->send($this->acceptedRequest());
        $second = $provider->send($this->acceptedRequest());

        $this->assertTrue($first->accepted);
        $this->assertTrue($second->accepted);
        if ($this->referenceIsIdempotent()) {
            $this->assertSame($first->providerMessageId, $second->providerMessageId);
        }
    }

    public function test_a_non_live_adapter_never_touches_the_network(): void
    {
        $provider = $this->makeProvider();
        if ($provider->isLive()) {
            $this->markTestSkipped('Adaptateur réel : ses appels HTTP doivent être simulés par Http::fake() dans son propre test.');
        }

        $provider->send($this->acceptedRequest());

        Http::assertNothingSent();
    }

    public function test_activation_lock_applies_through_the_manager(): void
    {
        $provider = $this->makeProvider();
        $manager = new SmsManager;
        $manager->extend($provider->name(), fn () => $provider);

        config(['sms.sending_enabled' => false]);
        if ($provider->isLive()) {
            $this->expectException(SmsSendingDisabledException::class);
        }
        $this->assertSame($provider, $manager->provider($provider->name()));
    }

    public function test_live_adapter_resolves_once_sending_is_explicitly_enabled(): void
    {
        $provider = $this->makeProvider();
        $manager = new SmsManager;
        $manager->extend($provider->name(), fn () => $provider);

        config(['sms.sending_enabled' => true]);

        $this->assertSame($provider, $manager->provider($provider->name()));
    }

    public function test_dispatcher_integration_records_provider_name_and_simulated_flag(): void
    {
        $provider = $this->makeProvider();
        app(SmsManager::class)->extend($provider->name(), fn () => $provider);
        config(['sms.provider' => $provider->name(), 'sms.sending_enabled' => true]);

        $company = Company::createFor(User::factory()->create(), 'Contrat');
        app(CreditService::class)->credit($company, 600000, 'seed:contract');
        app(CurrentCompany::class)->set($company);
        $contact = Contact::create(['phone' => self::DISPATCH_PHONE]);
        app(ConsentService::class)->record($contact, 'web_form', 'Case cochée', now()->subDay());

        $message = app(SmsDispatcher::class)->dispatch($contact, 'Bonjour (test)', 'contract-key-1');

        $this->assertSame(SmsStatus::Sent, $message->status);
        $this->assertSame($provider->name(), $message->provider);
        $this->assertSame(! $provider->isLive(), $message->simulated);
        $this->assertNotEmpty($message->provider_message_id);
    }
}
