<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan 8: the turn outcome (answered, no_source, tool_ok, ai_timeout, ...)
 * so operations metrics can count retrieval no-match without reading
 * message text. Metrics read turns by started_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_turns', function (Blueprint $table) {
            $table->string('outcome', 64)->nullable()->after('error_code');
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('ai_turns', function (Blueprint $table) {
            $table->dropIndex(['started_at']);
            $table->dropColumn('outcome');
        });
    }
};
