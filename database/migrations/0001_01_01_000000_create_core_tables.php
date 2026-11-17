<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('name');
            $table->unsignedSmallInteger('quiz_question_count')->default(8);
            $table->string('quiz_from')->nullable(); // key of another track whose question bank is reused
            $table->boolean('requires_policies')->default(true);
            $table->boolean('kiosk_enabled')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('training_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('name');
            $table->foreignId('track_id')->constrained()->restrictOnDelete();
            $table->json('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('location')->nullable();
            $table->string('hex_color', 7)->default('#64748B');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('microsoft_id')->nullable()->unique();
            $table->string('name');
            $table->string('email')->nullable()->unique();
            $table->string('employee_code')->nullable()->unique();
            $table->string('password')->nullable();
            $table->string('role')->default('employee')->index();
            $table->foreignId('training_group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->string('training_requirement_note')->nullable();
            $table->string('locale', 5)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->boolean('is_current')->default(false)->index();
            $table->timestamps();
        });

        Schema::create('policies', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->json('title');
            $table->string('version')->default('1');
            $table->date('effective_date')->nullable();
            $table->string('document_url')->nullable();
            $table->json('key_rules')->nullable();
            $table->boolean('is_mandatory_all')->default(true);
            $table->json('track_keys')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('policy_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('policy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('policy_version');
            $table->timestamp('acknowledged_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('supervised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['user_id', 'policy_id', 'campaign_id', 'policy_version'], 'ack_unique');
        });

        Schema::create('policy_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->text('declaration_text');
            $table->timestamp('declared_at');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('supervised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('training_modules', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->unsignedSmallInteger('estimated_minutes')->default(5);
            $table->json('slides');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('track_module', function (Blueprint $table) {
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->primary(['track_id', 'training_module_id']);
        });

        Schema::create('module_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('training_module_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->timestamp('completed_at');
            $table->foreignId('supervised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['user_id', 'training_module_id', 'campaign_id'], 'completion_unique');
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('pack_key')->nullable()->unique();
            $table->json('question_text');
            $table->json('options');
            $table->string('correct_option', 1);
            $table->json('explanation')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('total_questions');
            $table->decimal('percentage', 5, 2);
            $table->boolean('passed');
            $table->json('answers_payload');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at');
            $table->string('ip_address', 45)->nullable();
            $table->foreignId('supervised_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('training_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->foreignId('training_group_id')->nullable()->constrained()->nullOnDelete();
            $table->date('session_date');
            $table->string('location')->nullable();
            $table->string('trainer_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('session_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('training_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('attendance_type');
            $table->timestamp('recorded_at');
            $table->unique(['training_session_id', 'user_id']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->index();
            $table->foreignId('target_user_id')->nullable()->index();
            $table->string('action')->index();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('prev_hash', 64)->nullable();
            $table->string('hash', 64);
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'session_attendances', 'training_sessions', 'quiz_attempts', 'quiz_questions',
            'module_completions', 'track_module', 'training_modules', 'policy_declarations', 'policy_acknowledgments',
            'policies', 'campaigns', 'sessions', 'users', 'departments', 'training_groups', 'tracks', 'settings'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
