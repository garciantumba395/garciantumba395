<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            // Solde courant (en unités). Source de vérité = grand livre ; ce champ en est la somme, tenue à jour atomiquement.
            $t->bigInteger('credit_balance_units')->default(0);
        });
        if (DB::getDriverName() === 'pgsql') {
            // Filet de sécurité : le solde ne peut jamais être négatif, même en cas de bug applicatif.
            DB::statement('ALTER TABLE companies ADD CONSTRAINT companies_credit_balance_non_negative CHECK (credit_balance_units >= 0)');
        }

        // Grand livre en AJOUT SEUL : une écriture n'est jamais modifiée ni supprimée (correction = nouvelle écriture).
        Schema::create('credit_ledger_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20);                       // credit, debit, refund, adjustment
            $t->bigInteger('amount_units');               // signé : + crédit/remboursement, − débit
            $t->bigInteger('balance_after_units');
            $t->string('idempotency_key', 120);
            $t->unsignedBigInteger('sms_message_id')->nullable();
            $t->string('reason')->nullable();
            $t->boolean('simulated')->default(false);     // true = lié à un envoi fictif
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            // Protection contre les doublons de débit : une clé = une seule écriture par entreprise.
            $t->unique(['company_id', 'idempotency_key']);
            $t->index(['company_id', 'created_at']);
            $t->index('sms_message_id');
        });

        // Tarifs par pays et, au choix, par préfixe d'opérateur. Saisis par l'administrateur de la plateforme.
        Schema::create('sms_rates', function (Blueprint $t) {
            $t->id();
            $t->string('country', 2);
            $t->string('prefix', 20)->nullable();         // ex. "+24381" (E.164) ; null = tout le pays
            $t->string('label')->nullable();              // ex. nom de l'opérateur
            $t->unsignedInteger('units_per_segment');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['country', 'prefix']);
        });

        Schema::table('sms_messages', function (Blueprint $t) {
            $t->unsignedSmallInteger('segments')->nullable();
            $t->string('encoding', 10)->nullable();
            $t->unsignedInteger('cost_units')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('sms_messages', fn (Blueprint $t) => $t->dropColumn(['segments', 'encoding', 'cost_units']));
        Schema::dropIfExists('sms_rates');
        Schema::dropIfExists('credit_ledger_entries');
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_credit_balance_non_negative');
        }
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('credit_balance_units'));
    }
};
