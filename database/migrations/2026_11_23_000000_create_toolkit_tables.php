<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ISMS toolkit: the forms, procedures and guides people use in their daily work once the
 * policies are approved, plus the filled-in forms they submit back to the security officer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Supervisors, HR and admins see the toolkit by role; this grants it to anyone else.
            $table->boolean('can_access_toolkit')->default(false)->after('role');
        });

        Schema::create('toolkit_items', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type')->index(); // form | procedure | guide
            $table->json('title');
            $table->json('summary')->nullable();
            $table->json('when_to_use')->nullable();
            $table->json('roles_guidance')->nullable();
            $table->json('example_usage')->nullable();
            $table->string('file_path')->nullable(); // inside storage/app/pack/toolkit
            $table->string('original_filename')->nullable();
            $table->json('related_codes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('toolkit_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('toolkit_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reference_note')->nullable();
            $table->string('file_path'); // inside storage/app/toolkit-submissions
            $table->string('original_filename');
            $table->string('emailed_to')->nullable();
            $table->timestamp('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('toolkit_submissions');
        Schema::dropIfExists('toolkit_items');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('can_access_toolkit'));
    }
};
