<?php

namespace Database\Seeders;

use App\Domain\Consent\ConsentService;
use App\Domain\Credits\CreditService;
use App\Models\Campaign;
use App\Models\Company;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Données de DÉMONSTRATION, entièrement fictives (noms inventés, numéros fictifs +243 81 0000xxx), pour vérifier l'interface.
 * Refuse de s'exécuter en production. Les mots de passe sont générés au hasard et AFFICHÉS une seule fois dans la console :
 * rien n'est écrit dans le dépôt. À lancer : php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder refuse de tourner en production.');
        }

        $password = Str::random(16);
        $hash = Hash::make($password);
        $admin = $this->user('Admin Plateforme (démo)', 'admin-demo@example.test', $hash);
        $admin->forceFill(['is_platform_admin' => true])->save();
        $owner = $this->user('Marie Propriétaire (démo)', 'proprio-demo@example.test', $hash);
        $member = $this->user('Paul Membre (démo)', 'membre-demo@example.test', $hash);

        $company = Company::where('slug', 'like', 'demo-boutique%')->first() ?? Company::createFor($owner, 'Boutique Démo');
        $company->users()->syncWithoutDetaching([$member->id => ['role' => 'member']]);
        $other = Company::where('slug', 'like', 'demo-autre%')->first() ?? Company::createFor($this->user('Autre Entreprise (démo)', 'autre-demo@example.test', $hash), 'Autre Entreprise Démo');

        app(CurrentCompany::class)->runAs($company, function () use ($company, $owner) {
            if (Contact::count() > 0) {
                return;
            }
            $consent = app(ConsentService::class);
            $vip = ContactGroup::create(['name' => 'Clients VIP']);
            $news = ContactGroup::create(['name' => 'Newsletter']);
            $names = ['Alice', 'Bruno', 'Chantal', 'David', 'Esther', 'Fabrice', 'Grace', 'Henri', 'Irène', 'Jean', 'Karine', 'Luc'];
            foreach ($names as $i => $n) {
                $c = Contact::create(['name' => "$n (démo)", 'phone' => '+243810000'.str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT)]);
                $vip->addContacts($i % 3 === 0 ? [$c->id] : []);
                $news->addContacts([$c->id]);
                if ($i < 9) {
                    $consent->record($c, 'web_form', 'Case cochée sur le formulaire de démonstration', now()->subDays(10));
                }
                if ($i === 8) {
                    $consent->optOut($c->phone, 'manual', 'Demande de démonstration', $owner->id);
                }
            }
            app(CreditService::class)->credit($company, 600000, 'demo:seed', 'Crédits de démonstration (100 crédits)');
            $campaign = new Campaign(['name' => 'Promo de démonstration', 'body' => 'Bonjour ! -20 % ce week-end à la Boutique Démo.', 'contact_group_id' => $news->id]);
            $campaign->created_by = $owner->id;
            $campaign->save();
        });

        $this->command?->info('Données de démonstration prêtes (entièrement fictives).');
        $this->command?->warn("Mot de passe des comptes démo (affiché une seule fois) : $password");
        $this->command?->line('  admin-demo@example.test (plateforme) | proprio-demo@example.test (propriétaire) | membre-demo@example.test (membre) | autre-demo@example.test (autre entreprise)');
    }

    private function user(string $name, string $email, string $hash): User
    {
        $u = User::firstOrNew(['email' => $email]);
        $u->forceFill(['name' => $name, 'password' => $hash, 'email_verified_at' => now()])->save();

        return $u;
    }
}
