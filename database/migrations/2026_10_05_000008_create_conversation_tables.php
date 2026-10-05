<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->jsonb('scene_context');
            $table->unsignedInteger('context_revision')->default(1);
            $table->timestampTz('last_activity_at');
            $table->timestamps();

            $table->index(['user_id', 'last_activity_at']);
        });

        Schema::create('ai_turns', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->uuid('client_turn_id');
            $table->string('request_fingerprint', 64);
            $table->string('status', 16);
            $table->string('locale', 5);
            $table->jsonb('context_snapshot');
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('deadline_at');
            $table->string('error_code', 64)->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('model', 160)->nullable();
            $table->string('prompt_version', 32);
            $table->jsonb('usage')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'client_turn_id']);
            $table->index(['status', 'started_at']);
        });

        Schema::create('conversation_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('turn_id')->nullable()->constrained('ai_turns')->nullOnDelete();
            $table->string('role', 16);
            $table->text('text');
            $table->jsonb('ui')->nullable();
            $table->timestampTz('created_at');

            $table->index(['conversation_id', 'id']);
        });

        Schema::create('turn_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained('ai_turns')->cascadeOnDelete();
            $table->foreignId('revision_id')->constrained('knowledge_revisions')->restrictOnDelete();
            $table->string('locale', 5);
            $table->string('excerpt_hash', 64);

            $table->unique(['turn_id', 'revision_id', 'locale']);
            $table->index('revision_id');
        });

        // Filled by Plan 7 (tool calling).
        Schema::create('tool_invocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turn_id')->constrained('ai_turns')->cascadeOnDelete();
            $table->string('call_id', 128);
            $table->string('name', 64);
            $table->string('status', 32);
            $table->jsonb('validated_input')->nullable();
            $table->jsonb('result_reference')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('finished_at')->nullable();

            $table->index(['turn_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_invocations');
        Schema::dropIfExists('turn_evidence');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('ai_turns');
        Schema::dropIfExists('conversations');
    }
};
