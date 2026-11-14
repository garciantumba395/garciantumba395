<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            // Pas de clé étrangère : le journal survit à la suppression du contact (le numéro est copié dans `phone`).
            $t->unsignedBigInteger('contact_id')->nullable();
            $t->string('idempotency_key', 120);
            $t->string('phone', 20);
            $t->text('body');
            $t->string('sender_id', 20)->nullable();
            $t->string('status', 20)->default('pending');
            $t->string('blocked_reason', 30)->nullable();
            $t->string('provider', 30)->nullable();
            $t->boolean('simulated')->default(true);     // true = aucun SMS réel n'est parti
            $t->string('provider_message_id', 100)->nullable();
            $t->string('error_code', 50)->nullable();
            $t->string('error_message', 500)->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('sending_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->timestamps();

            // Protection contre les doublons : une clé = un seul message par entreprise.
            $t->unique(['company_id', 'idempotency_key']);
            $t->index(['company_id', 'status']);
            $t->index(['company_id', 'created_at']);
            $t->unique(['provider', 'provider_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};
