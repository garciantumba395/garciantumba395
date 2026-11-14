<?php

use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\Webhooks\SmsWebhookController;
use App\Livewire\Admin\AuditLogs as AdminAuditLogs;
use App\Livewire\Admin\Companies as AdminCompanies;
use App\Livewire\Admin\CompanyShow as AdminCompanyShow;
use App\Livewire\Admin\Dashboard as AdminDashboard;
use App\Livewire\Admin\Rates as AdminRates;
use App\Livewire\Admin\Users as AdminUsers;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Auth\TwoFactorChallenge;
use App\Livewire\Campaigns\Form as CampaignForm;
use App\Livewire\Campaigns\Index as CampaignIndex;
use App\Livewire\Campaigns\Show as CampaignShow;
use App\Livewire\Companies\Create as CompanyCreate;
use App\Livewire\Companies\Members;
use App\Livewire\Companies\Settings as CompanySettings;
use App\Livewire\Contacts\Form as ContactForm;
use App\Livewire\Contacts\Import as ContactImport;
use App\Livewire\Contacts\Index as ContactIndex;
use App\Livewire\Contacts\Show as ContactShow;
use App\Livewire\Credits\Index as CreditIndex;
use App\Livewire\Dashboard;
use App\Livewire\Groups\Index as GroupIndex;
use App\Livewire\Messages\Index as MessageIndex;
use App\Livewire\Profile\Show as ProfileShow;
use App\Livewire\Reports\Index as ReportIndex;
use App\Livewire\Suppressions\Index as SuppressionIndex;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

Route::redirect('/', '/connexion');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', Login::class)->name('login');
    Route::get('/inscription', Register::class)->name('register');
    Route::get('/mot-de-passe-oublie', ForgotPassword::class)->name('password.request');
    Route::get('/reinitialiser-mot-de-passe/{token}', ResetPassword::class)->name('password.reset');
    Route::get('/connexion/verification', TwoFactorChallenge::class)->name('two-factor.challenge'); // étape 9
});

Route::middleware(['auth', 'two_factor.admins'])->group(function () {
    Route::get('/profil', ProfileShow::class)->name('profile'); // étape 9
    Route::post('/deconnexion', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Création d'entreprise : accessible sans entreprise active.
    Route::get('/entreprises/nouvelle', CompanyCreate::class)->name('companies.create');

    // Administration de la plateforme : réservée à is_platform_admin, sans entreprise active requise.
    Route::middleware('platform.admin')->prefix('admin')->group(function () {
        Route::get('/entreprises', AdminCompanies::class)->name('admin.companies');
        Route::get('/tarifs', AdminRates::class)->name('admin.rates');
        // --- Étape 8 : administration et audit ---
        Route::get('/', AdminDashboard::class)->name('admin.dashboard');
        Route::get('/entreprises/{company}', AdminCompanyShow::class)->whereNumber('company')->name('admin.companies.show');
        Route::get('/utilisateurs', AdminUsers::class)->name('admin.users');
        Route::get('/audit', AdminAuditLogs::class)->name('admin.audit');
        // --- fin étape 8 ---
    });

    Route::middleware('company')->group(function () {
        Route::get('/tableau-de-bord', Dashboard::class)->name('dashboard');
        Route::get('/entreprise/parametres', CompanySettings::class)->name('companies.settings');
        Route::get('/entreprise/membres', Members::class)->name('companies.members');
        Route::get('/contacts', ContactIndex::class)->name('contacts.index');
        Route::get('/contacts/nouveau', ContactForm::class)->name('contacts.create');
        Route::get('/contacts/importer', ContactImport::class)->name('contacts.import');
        Route::get('/contacts/{contact}/modifier', ContactForm::class)->whereNumber('contact')->name('contacts.edit');
        Route::get('/contacts/{contact}', ContactShow::class)->whereNumber('contact')->name('contacts.show');
        Route::get('/desinscriptions', SuppressionIndex::class)->name('suppressions.index');
        Route::get('/messages', MessageIndex::class)->name('messages.index');
        Route::get('/campagnes', CampaignIndex::class)->name('campaigns.index');
        Route::get('/campagnes/nouvelle', CampaignForm::class)->name('campaigns.create');
        Route::get('/campagnes/{campaign}', CampaignShow::class)->whereNumber('campaign')->name('campaigns.show');
        Route::get('/campagnes/{campaign}/modifier', CampaignForm::class)->whereNumber('campaign')->name('campaigns.edit');
        Route::get('/credits', CreditIndex::class)->name('credits.index');
        Route::get('/groupes', GroupIndex::class)->name('groups.index');
        // --- Étape 7 : rapports d'envoi (agrégats : tous rôles ; export CSV : owner/admin, vérifié dans le contrôleur) ---
        Route::get('/rapports', ReportIndex::class)->name('reports.index');
        Route::get('/rapports/export', ReportExportController::class)->name('reports.export');
        // --- fin étape 7 ---
    });
});

// --- Étape 10 (préparatoire) : webhooks des fournisseurs SMS (agent D) ---
// Hors session et hors CSRF (appelés par un serveur tiers) : l'authentification est la signature HMAC, vérifiée dans le contrôleur.
Route::post('/webhooks/sms/{provider}', SmsWebhookController::class)
    ->where('provider', '[a-z0-9_-]{1,30}')
    ->middleware('throttle:sms-webhooks')
    ->withoutMiddleware([
        EncryptCookies::class,
        AddQueuedCookiesToResponse::class,
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->name('webhooks.sms');
// --- Fin étape 10 (préparatoire) ---
