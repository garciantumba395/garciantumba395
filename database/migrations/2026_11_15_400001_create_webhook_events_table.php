<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Déduplication des webhooks : identité de l'événement SEULEMENT (ni corps, ni numéro, ni texte : données personnelles).
        // Table technique globale, sans company_id : un webhook arrive sans contexte d'entreprise.
        Schema::create('webhook_events', function (Blueprint $t) {
            $t->id();
            $t->string('provider', 30);
            $t->string('event_id', 120);
            $t->string('type', 30);
            $t->string('outcome', 30)->nullable();
            $t->timestamp('received_at');
            $t->unique(['provider', 'event_id']);
            $t->index('received_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
