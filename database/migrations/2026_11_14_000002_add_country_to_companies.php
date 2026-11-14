<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            // Code pays ISO 3166-1 alpha-2 : sert à normaliser les numéros saisis sans indicatif.
            $t->string('country', 2)->default('CD')->after('slug');
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('country'));
    }
};
