<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_basic_headers_are_present_on_public_pages(): void
    {
        $r = $this->get('/connexion')->assertOk();
        $r->assertHeader('X-Content-Type-Options', 'nosniff');
        $r->assertHeader('X-Frame-Options', 'DENY');
        $r->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=()', $r->headers->get('Permissions-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
    }

    public function test_headers_are_present_on_authenticated_pages_redirects_and_errors(): void
    {
        $user = User::factory()->create();
        Company::createFor($user, 'Acme');

        $this->get('/tableau-de-bord')->assertRedirect()->assertHeader('X-Content-Type-Options', 'nosniff'); // invité : redirection
        $this->actingAs($user)->get('/tableau-de-bord')->assertHeader('X-Frame-Options', 'DENY');
        $this->get('/page-inexistante')->assertNotFound()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/up')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_hsts_only_over_https(): void
    {
        $this->get('http://localhost/connexion')->assertHeaderMissing('Strict-Transport-Security');

        $r = $this->get('https://localhost/connexion');
        $r->assertHeader('Strict-Transport-Security', 'max-age=15552000');
    }

    public function test_hsts_is_configurable(): void
    {
        config(['security.headers.hsts_max_age' => 600, 'security.headers.hsts_include_subdomains' => true]);
        $this->get('https://localhost/connexion')->assertHeader('Strict-Transport-Security', 'max-age=600; includeSubDomains');

        config(['security.headers.hsts' => false]);
        $this->get('https://localhost/connexion')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_csp_can_be_report_only_or_disabled(): void
    {
        config(['security.headers.csp_report_only' => true]);
        $r = $this->get('/connexion');
        $r->assertHeaderMissing('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy-Report-Only'));

        config(['security.headers.csp' => '']);
        $this->get('/connexion')->assertHeaderMissing('Content-Security-Policy')->assertHeaderMissing('Content-Security-Policy-Report-Only');
    }

    public function test_default_csp_does_not_restrict_scripts_so_livewire_keeps_working(): void
    {
        $csp = (string) $this->get('/connexion')->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('script-src', $csp);
        $this->assertStringNotContainsString('default-src', $csp);
    }

    public function test_existing_header_is_not_overwritten(): void
    {
        Route::middleware('web')->get('/__test-headers', fn () => response('ok')->header('X-Frame-Options', 'SAMEORIGIN'));
        $this->get('/__test-headers')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('X-Content-Type-Options', 'nosniff');
    }
}
