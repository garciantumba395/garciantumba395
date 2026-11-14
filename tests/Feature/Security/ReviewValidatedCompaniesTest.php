<?php

namespace Tests\Feature\Security;

use App\Domain\Sms\SendingPolicy;
use App\Domain\Sms\SendingStatus;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ReviewValidatedCompaniesTest extends TestCase
{
    use RefreshDatabase;

    private Company $a;

    private Company $b;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.review.min_sample' => 5, 'security.review.window_days' => 7]);
        $this->a = $this->validatedCompany('A');
        $this->b = $this->validatedCompany('B');
    }

    private function validatedCompany(string $name): Company
    {
        $company = Company::createFor(User::factory()->create(), $name);
        DB::table('companies')->where('id', $company->id)->update([
            'sending_status' => 'validated',
            'sending_status_changed_at' => now()->subDays(30),
            'sending_status_reason' => 'Validation initiale',
        ]);

        return $company->fresh();
    }

    /** Insère des messages bruts (journal) : $status 'sent'|'failed', réels ou simulés, datés. */
    private function messages(Company $c, int $count, string $status, bool $simulated = false, int $daysAgo = 1): void
    {
        for ($i = 0; $i < $count; $i++) {
            DB::table('sms_messages')->insert([
                'company_id' => $c->id, 'idempotency_key' => 'k'.(++$this->seq), 'phone' => '+24381'.str_pad((string) $this->seq, 7, '0', STR_PAD_LEFT),
                'body' => 'x', 'status' => $status, 'simulated' => $simulated, 'created_at' => now()->subDays($daysAgo), 'updated_at' => now()->subDays($daysAgo),
            ]);
        }
    }

    private function statusOf(Company $c): SendingStatus
    {
        return $c->fresh()->sending_status;
    }

    private function reviewOf(Company $c): bool
    {
        return app(SendingPolicy::class)->reviewValidated($c->id);
    }

    public function test_validated_company_with_high_failure_rate_is_suspended(): void
    {
        $this->messages($this->a, 6, 'sent');
        $this->messages($this->a, 4, 'failed'); // 40 % > 20 %

        $this->assertTrue($this->reviewOf($this->a));
        $this->assertSame(SendingStatus::Suspended, $this->statusOf($this->a));
        $fresh = $this->a->fresh();
        $this->assertStringContainsString('surveillance', $fresh->sending_status_reason);
        $this->assertStringContainsString("taux d'échec", $fresh->sending_status_reason);
        $this->assertTrue($fresh->sending_status_changed_at->isToday());
    }

    public function test_healthy_company_is_not_suspended(): void
    {
        $this->messages($this->a, 9, 'sent');
        $this->messages($this->a, 1, 'failed'); // 10 % <= 20 %

        $this->assertFalse($this->reviewOf($this->a));
        $this->assertSame(SendingStatus::Validated, $this->statusOf($this->a));
        $this->assertSame('Validation initiale', $this->a->fresh()->sending_status_reason);
    }

    public function test_simulated_messages_are_excluded(): void
    {
        $this->messages($this->a, 20, 'failed', simulated: true);
        $this->messages($this->a, 6, 'sent');

        $this->assertFalse($this->reviewOf($this->a));
        $this->assertSame(SendingStatus::Validated, $this->statusOf($this->a));
    }

    public function test_only_simulated_messages_never_reach_the_minimal_sample(): void
    {
        $this->messages($this->a, 50, 'failed', simulated: true);
        $this->assertFalse($this->reviewOf($this->a));
    }

    public function test_insufficient_sample_is_not_judged(): void
    {
        $this->messages($this->a, 4, 'failed'); // 100 % d'échec mais 4 < 5 envois

        $this->assertFalse($this->reviewOf($this->a));
        $this->assertSame(SendingStatus::Validated, $this->statusOf($this->a));
    }

    public function test_already_suspended_company_is_left_untouched(): void
    {
        DB::table('companies')->where('id', $this->a->id)->update(['sending_status' => 'suspended', 'sending_status_reason' => 'Décision manuelle']);
        $this->messages($this->a, 10, 'failed');

        $this->assertFalse($this->reviewOf($this->a));
        $this->assertSame('Décision manuelle', $this->a->fresh()->sending_status_reason);
    }

    public function test_probation_company_is_not_handled_by_the_review(): void
    {
        DB::table('companies')->where('id', $this->a->id)->update(['sending_status' => 'probation']);
        $this->messages($this->a, 10, 'failed');

        $this->assertFalse($this->reviewOf($this->a));
        $this->assertSame(SendingStatus::Probation, $this->statusOf($this->a));
    }

    public function test_unknown_company_is_ignored(): void
    {
        $this->assertFalse(app(SendingPolicy::class)->reviewValidated(999999));
    }

    public function test_messages_outside_the_sliding_window_are_ignored(): void
    {
        $this->messages($this->a, 10, 'failed', daysAgo: 10); // hors fenêtre de 7 jours
        $this->messages($this->a, 6, 'sent', daysAgo: 1);

        $this->assertFalse($this->reviewOf($this->a));

        config(['security.review.window_days' => 14]);
        $this->assertTrue($this->reviewOf($this->a)); // la fenêtre configurable les inclut : 10/16 = 62 %
    }

    public function test_messages_before_the_last_status_change_are_not_rejudged(): void
    {
        // Un administrateur a revalidé l'entreprise il y a 1 jour ; les échecs d'il y a 3 jours ne comptent plus.
        DB::table('companies')->where('id', $this->a->id)->update(['sending_status_changed_at' => now()->subDay()]);
        $this->messages($this->a, 10, 'failed', daysAgo: 3);
        $this->messages($this->a, 6, 'sent', daysAgo: 0);

        $this->assertFalse($this->reviewOf($this->a));
        $this->assertSame(SendingStatus::Validated, $this->statusOf($this->a));
    }

    public function test_high_optout_rate_suspends(): void
    {
        $this->messages($this->a, 10, 'sent');
        $phones = DB::table('sms_messages')->where('company_id', $this->a->id)->limit(2)->pluck('phone');
        foreach ($phones as $phone) { // 2 désinscriptions sur 10 envois réels = 20 % > 5 %
            DB::table('suppressions')->insert(['company_id' => $this->a->id, 'phone' => $phone, 'source' => 'manual', 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->assertTrue($this->reviewOf($this->a));
        $this->assertStringContainsString('désinscription', $this->a->fresh()->sending_status_reason);
    }

    public function test_thresholds_are_the_existing_ones_and_configurable(): void
    {
        $this->messages($this->a, 8, 'sent');
        $this->messages($this->a, 2, 'failed'); // 20 % : pas > 20 %
        $this->assertFalse($this->reviewOf($this->a));

        config(['sms.sending.max_failure_rate' => 0.1]);
        $this->assertTrue($this->reviewOf($this->a));
    }

    public function test_isolation_between_companies(): void
    {
        $this->messages($this->b, 10, 'failed'); // B dérive
        $this->messages($this->a, 10, 'sent');   // A est saine

        $this->assertFalse($this->reviewOf($this->a));
        $this->assertTrue($this->reviewOf($this->b));
        $this->assertSame(SendingStatus::Validated, $this->statusOf($this->a));
        $this->assertSame(SendingStatus::Suspended, $this->statusOf($this->b));
    }

    public function test_command_suspends_only_the_drifting_company_and_logs_ids_only(): void
    {
        $this->messages($this->a, 10, 'sent');
        $this->messages($this->b, 10, 'failed');
        Log::spy();

        $this->assertSame(0, Artisan::call('sending:review'));
        $this->assertStringContainsString('2 entreprise(s) validée(s) examinée(s), 1 suspendue(s)', Artisan::output());

        $this->assertSame(SendingStatus::Validated, $this->statusOf($this->a));
        $this->assertSame(SendingStatus::Suspended, $this->statusOf($this->b));
        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($message, $context) => $context === ['company_ids' => [$this->b->id]] && ! str_contains($message, 'B'));
    }

    public function test_command_is_idempotent_and_silent_when_nothing_to_do(): void
    {
        $this->messages($this->a, 10, 'sent');
        Log::spy();

        Artisan::call('sending:review');
        Artisan::call('sending:review');
        $this->assertStringContainsString('0 suspendue(s)', Artisan::output());
        Log::shouldNotHaveReceived('warning');
    }

    public function test_suspended_company_blocks_real_sends_after_review(): void
    {
        $this->messages($this->b, 10, 'failed');
        Artisan::call('sending:review');

        $this->assertSame('suspended', app(SendingPolicy::class)->blockReason($this->b->id, 0));
        $this->assertNull(app(SendingPolicy::class)->blockReason($this->a->id, 0));
    }

    public function test_review_and_credits_verification_are_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());
        $find = fn (string $name) => $events->first(fn ($e) => str_contains($e->command, $name));

        $this->assertSame('0 3 * * *', $find('sending:review')->expression);
        $this->assertSame('30 3 * * *', $find('credits:verify')->expression);
        $this->assertTrue($find('sending:review')->withoutOverlapping);
        $this->assertTrue($find('credits:verify')->withoutOverlapping);
    }

    public function test_credits_verify_failure_is_logged_as_error_without_data(): void
    {
        DB::table('companies')->where('id', $this->a->id)->update(['credit_balance_units' => 12345]); // incohérent avec un grand livre vide
        Log::spy();

        $this->assertSame(1, Artisan::call('credits:verify'));
        Log::shouldHaveReceived('error')->once()->withArgs(fn ($message) => str_contains($message, 'credits:verify')
            && ! str_contains($message, '12345') && ! str_contains($message, (string) $this->a->name));
    }

    public function test_credits_verify_success_logs_nothing(): void
    {
        Log::spy();
        $this->assertSame(0, Artisan::call('credits:verify'));
        Log::shouldNotHaveReceived('error');
    }
}
