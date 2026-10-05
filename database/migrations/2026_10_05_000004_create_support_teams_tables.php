<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_teams', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('name', 160);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Members must hold the staff role; enforced by UpdateUserAccess.
        Schema::create('support_team_user', function (Blueprint $table) {
            $table->foreignId('support_team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['support_team_id', 'user_id']);
            $table->index('user_id');
        });

        // Authoring departments for editors; enforced by UpdateUserAccess.
        Schema::create('knowledge_editor_department', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'department_id']);
            $table->index('department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_editor_department');
        Schema::dropIfExists('support_team_user');
        Schema::dropIfExists('support_teams');
    }
};
