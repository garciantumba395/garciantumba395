<?php

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\ResetPassword;
use App\Livewire\Companies\Create as CompanyCreate;
use App\Livewire\Companies\Members;
use App\Livewire\Companies\Settings as CompanySettings;
use App\Livewire\Contacts\Form as ContactForm;
use App\Livewire\Contacts\Import as ContactImport;
use App\Livewire\Contacts\Index as ContactIndex;
use App\Livewire\Contacts\Show as ContactShow;
use App\Livewire\Dashboard;
use App\Livewire\Groups\Index as GroupIndex;
use App\Livewire\Suppressions\Index as SuppressionIndex;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/connexion');

Route::middleware('guest')->group(function () {
    Route::get('/connexion', Login::class)->name('login');
    Route::get('/inscription', Register::class)->name('register');
    Route::get('/mot-de-passe-oublie', ForgotPassword::class)->name('password.request');
    Route::get('/reinitialiser-mot-de-passe/{token}', ResetPassword::class)->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', function () {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    // Création d'entreprise : accessible sans entreprise active.
    Route::get('/entreprises/nouvelle', CompanyCreate::class)->name('companies.create');

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
        Route::get('/groupes', GroupIndex::class)->name('groups.index');
    });
});
