<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            // D12 : pas d'approbation manuelle avant le réel ; démarrage limité, validation/suspension automatiques.
            $t->string('sending_status', 12)->default('probation');
            $t->timestamp('sending_status_changed_at')->nullable();
            $t->string('sending_status_reason')->nullable();
            $t->unsignedInteger('daily_send_limit')->nullable();   // surcharge de la limite journalière (envois réels)
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn(['sending_status', 'sending_status_changed_at', 'sending_status_reason', 'daily_send_limit']));
    }
};
