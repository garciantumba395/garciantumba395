<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('name')->nullable();
            $t->string('phone', 20);          // E.164, ex. +243812345678
            $t->string('email')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'phone']);   // un numéro = un contact par entreprise
            $t->unique(['id', 'company_id']);      // cible des clés étrangères composites
            $t->index(['company_id', 'name']);
        });

        Schema::create('contact_groups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->timestamps();
            $t->unique(['company_id', 'name']);
            $t->unique(['id', 'company_id']);
        });

        // Clés composites : impossible, même par bug applicatif, de lier un contact
        // et un groupe appartenant à deux entreprises différentes.
        Schema::create('contact_group_contact', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('contact_id');
            $t->unsignedBigInteger('contact_group_id');
            $t->timestamps();
            $t->primary(['contact_id', 'contact_group_id']);
            $t->foreign(['contact_id', 'company_id'])->references(['id', 'company_id'])->on('contacts')->cascadeOnDelete();
            $t->foreign(['contact_group_id', 'company_id'])->references(['id', 'company_id'])->on('contact_groups')->cascadeOnDelete();
            $t->index('contact_group_id');
        });

        Schema::create('contact_imports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedBigInteger('contact_group_id')->nullable();
            $t->string('filename');            // nom d'origine seulement : le fichier n'est pas conservé
            $t->unsignedInteger('total_rows')->default(0);
            $t->unsignedInteger('created_count')->default(0);
            $t->unsignedInteger('updated_count')->default(0);
            $t->unsignedInteger('invalid_count')->default(0);
            $t->unsignedInteger('duplicate_count')->default(0);
            $t->json('errors')->nullable();    // [{line, reason}] — sans numéros ni données personnelles
            $t->timestamps();
            $t->foreign(['contact_group_id', 'company_id'])->references(['id', 'company_id'])->on('contact_groups')->nullOnDelete();
            $t->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_imports');
        Schema::dropIfExists('contact_group_contact');
        Schema::dropIfExists('contact_groups');
        Schema::dropIfExists('contacts');
    }
};
