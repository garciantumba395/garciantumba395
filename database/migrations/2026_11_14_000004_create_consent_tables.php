<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preuves de consentement : ajout seul (révocation = renseigner revoked_at, jamais de suppression applicative).
        Schema::create('consents', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('contact_id');
            $t->string('source', 30);
            $t->text('evidence')->nullable();
            $t->timestamp('obtained_at');
            $t->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('revoked_at')->nullable();
            $t->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('revoke_reason')->nullable();
            $t->timestamps();
            $t->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            // Clé composite : un consentement ne peut viser qu'un contact de la même entreprise.
            $t->foreign(['contact_id', 'company_id'])->references(['id', 'company_id'])->on('contacts')->cascadeOnDelete();
            $t->index(['contact_id', 'revoked_at']);
        });

        // Désinscriptions indexées par NUMÉRO (et non par contact) : elles survivent à la suppression
        // et à la recréation du contact (ex. réimport), donc un numéro désinscrit ne redevient jamais ciblable par accident.
        Schema::create('suppressions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('phone', 20);
            $t->string('source', 30)->default('manual');
            $t->string('reason')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('lifted_at')->nullable();
            $t->foreignId('lifted_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('lift_reason')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'phone']);
        });
        // Une seule désinscription ACTIVE par numéro et par entreprise (index partiel : PostgreSQL et SQLite).
        DB::statement('CREATE UNIQUE INDEX suppressions_active_unique ON suppressions (company_id, phone) WHERE lifted_at IS NULL');

        Schema::table('contact_imports', function (Blueprint $t) {
            $t->unsignedInteger('consent_count')->default(0);
            $t->unsignedInteger('suppressed_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('contact_imports', fn (Blueprint $t) => $t->dropColumn(['consent_count', 'suppressed_count']));
        Schema::dropIfExists('suppressions');
        Schema::dropIfExists('consents');
    }
};
