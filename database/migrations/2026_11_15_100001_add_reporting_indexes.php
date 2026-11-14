<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index des rapports (étape 7). Toutes les requêtes de rapport filtrent sur
 *   company_id = ? AND simulated = ? AND created_at >= ? AND created_at < ?
 * puis agrègent (GROUP BY). L'index existant (company_id, created_at) oblige à lire toutes les lignes de la
 * période, réelles ET simulées, pour ne garder qu'un des deux blocs ; avec `simulated` au milieu, chaque bloc
 * lit uniquement ses propres lignes (la séparation simulé/réel devient aussi une séparation d'index).
 * Même raisonnement pour le grand livre (débits/remboursements d'un bloc sur une période).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_messages', function (Blueprint $t) {
            $t->index(['company_id', 'simulated', 'created_at'], 'sms_messages_report_idx');
        });
        Schema::table('credit_ledger_entries', function (Blueprint $t) {
            $t->index(['company_id', 'simulated', 'created_at'], 'credit_ledger_report_idx');
        });
    }

    public function down(): void
    {
        Schema::table('credit_ledger_entries', fn (Blueprint $t) => $t->dropIndex('credit_ledger_report_idx'));
        Schema::table('sms_messages', fn (Blueprint $t) => $t->dropIndex('sms_messages_report_idx'));
    }
};
