<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_drafts', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            // At most one draft per AI turn (Plan 7).
            $table->foreignId('originating_turn_id')->nullable()->unique()->constrained('ai_turns')->nullOnDelete();
            $table->foreignId('team_id')->constrained('support_teams')->restrictOnDelete();
            $table->string('category', 32);
            $table->string('subject', 160);
            $table->text('description');
            $table->unsignedInteger('revision')->default(1);
            $table->string('payload_fingerprint', 64);
            $table->timestampTz('expires_at');
            $table->timestamps();

            $table->index(['requester_id', 'created_at']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('reference', 32)->unique();
            $table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('team_id')->constrained('support_teams')->restrictOnDelete();
            $table->string('category', 32);
            $table->string('subject', 160);
            $table->text('description');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('open');
            $table->unsignedInteger('version')->default(1);
            // One ticket per draft; purge deletes the ticket first, then the draft.
            $table->foreignId('draft_id')->unique()->constrained('ticket_drafts')->restrictOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampTz('closed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status', 'updated_at']);
            $table->index(['requester_id', 'updated_at']);
            $table->index('assignee_id');
        });

        // Circular link added after both tables exist.
        Schema::table('ticket_drafts', function (Blueprint $table) {
            $table->foreignId('consumed_ticket_id')->nullable()->after('expires_at')->constrained('tickets')->nullOnDelete();
        });

        Schema::create('ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('visibility', 16);
            $table->text('text');
            $table->timestampTz('created_at');

            $table->index(['ticket_id', 'id']);
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_code', 32);
            $table->string('previous_status', 16)->nullable();
            $table->string('next_status', 16)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at');

            $table->index(['ticket_id', 'id']);
        });

        Schema::create('operation_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('operation_type', 48);
            $table->uuid('idempotency_key');
            $table->string('fingerprint', 64);
            $table->string('resource_type', 32);
            $table->string('resource_public_id', 64);
            $table->timestampTz('created_at');

            $table->unique(['actor_id', 'operation_type', 'idempotency_key']);
            $table->index(['resource_type', 'resource_public_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('operation_receipts');
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_messages');
        Schema::table('ticket_drafts', fn (Blueprint $table) => $table->dropConstrainedForeignId('consumed_ticket_id'));
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_drafts');
    }
};
