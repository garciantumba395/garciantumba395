<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Identité de l'expéditeur de publicité (art. 66 du Code du numérique) : nom, adresse, e-mail obligatoires pour l'envoi réel ;
        // RCCM, numéro d'impôt et acte autorisant l'activité : « éventuellement » (facultatifs).
        Schema::table('companies', function (Blueprint $t) {
            $t->string('legal_name')->nullable();
            $t->string('legal_address', 500)->nullable();
            $t->string('legal_email')->nullable();
            $t->string('legal_phone', 30)->nullable();
            $t->string('legal_rccm', 60)->nullable();
            $t->string('legal_tax_id', 60)->nullable();
            $t->string('legal_act')->nullable();
        });

        // Lien public de désabonnement (art. 348) : un code court et aléatoire par (entreprise, numéro). Le numéro y est conservé pour pouvoir
        // honorer l'opposition même si le contact est supprimé (art. 70, point 3 : liste tenue à jour).
        Schema::create('unsubscribe_links', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('phone', 20);
            $t->string('code', 32)->unique();
            $t->timestamp('used_at')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->unique(['company_id', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unsubscribe_links');
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn(['legal_name', 'legal_address', 'legal_email', 'legal_phone', 'legal_rccm', 'legal_tax_id', 'legal_act']));
    }
};
