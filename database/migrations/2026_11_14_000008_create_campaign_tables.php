<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->text('body');
            $t->unsignedBigInteger('contact_group_id')->nullable();   // null = tous les contacts ; résolu à la préparation
            $t->string('status', 12)->default('draft');
            $t->timestamp('scheduled_at')->nullable();
            $t->unsignedInteger('recipient_count')->default(0);       // figé à la préparation
            $t->unsignedInteger('excluded_count')->default(0);        // contacts du ciblage non ciblables à la préparation
            $t->string('pause_code', 30)->nullable();
            $t->string('pause_message')->nullable();
            $t->timestamp('paused_at')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamp('cancelled_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['id', 'company_id']);
            $t->index(['company_id', 'status']);
            $t->index(['status', 'scheduled_at']);
        });

        // Destinataires figés d'une campagne. UNIQUE(campaign_id, contact_id) : un contact ne reçoit une campagne qu'une fois.
        Schema::create('campaign_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('campaign_id');
            $t->unsignedBigInteger('contact_id');          // pas de FK : le suivi survit à la suppression du contact
            $t->string('phone', 20);
            $t->unsignedBigInteger('sms_message_id')->nullable();
            $t->unsignedSmallInteger('attempt')->default(0); // incrémenté après un blocage temporaire (nouvelle clé d'idempotence)
            $t->timestamp('processed_at')->nullable();
            $t->string('note', 60)->nullable();
            $t->timestamps();
            $t->unique(['campaign_id', 'contact_id']);
            $t->foreign(['campaign_id', 'company_id'])->references(['id', 'company_id'])->on('campaigns')->cascadeOnDelete();
            $t->index(['campaign_id', 'processed_at']);
            $t->index('sms_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_messages');
        Schema::dropIfExists('campaigns');
    }
};
