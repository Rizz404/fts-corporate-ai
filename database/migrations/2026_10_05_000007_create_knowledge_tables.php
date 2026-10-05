<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->foreignId('owner_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('owner_department_id');
        });

        Schema::create('knowledge_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledge_articles')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 16);
            $table->string('audience', 16)->default('common');
            $table->string('category', 16)->default('general');
            $table->timestampTz('effective_from')->nullable();
            $table->timestampTz('effective_until')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'version']);
            $table->index(['status', 'category']);
            $table->index(['status', 'effective_from', 'effective_until']);
        });

        // One published and at most one draft revision per article. Partial
        // indexes work on PostgreSQL (target) and SQLite (local).
        DB::statement("CREATE UNIQUE INDEX knowledge_revisions_one_published ON knowledge_revisions (article_id) WHERE status = 'published'");
        DB::statement("CREATE UNIQUE INDEX knowledge_revisions_one_draft ON knowledge_revisions (article_id) WHERE status = 'draft'");

        Schema::create('knowledge_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('revision_id')->constrained('knowledge_revisions')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('title', 180)->default('');
            $table->text('body');
            $table->jsonb('tags');
            $table->jsonb('aliases');
            // Derived, NFKC + lowercase; written by the model, used by retrieval.
            $table->text('search_title');
            $table->text('search_body');
            $table->text('search_keywords');
            $table->timestamps();

            $table->unique(['revision_id', 'locale']);
        });

        Schema::create('knowledge_revision_department', function (Blueprint $table) {
            $table->foreignId('revision_id')->constrained('knowledge_revisions')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->primary(['revision_id', 'department_id']);
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_revision_department');
        Schema::dropIfExists('knowledge_translations');
        DB::statement('DROP INDEX IF EXISTS knowledge_revisions_one_draft');
        DB::statement('DROP INDEX IF EXISTS knowledge_revisions_one_published');
        Schema::dropIfExists('knowledge_revisions');
        Schema::dropIfExists('knowledge_articles');
    }
};
