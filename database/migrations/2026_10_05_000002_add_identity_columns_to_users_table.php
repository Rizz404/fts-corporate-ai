<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Users are provisioned by admins only, so the table is empty when this
     * runs and department_id can be required immediately.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('department_id')->after('id')->constrained('departments')->restrictOnDelete();
            $table->string('preferred_locale', 5)->default('id')->after('password');
            $table->boolean('is_active')->default(true)->after('preferred_locale');
            $table->boolean('must_change_password')->default(true)->after('is_active');
            $table->index(['is_active', 'department_id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['is_active', 'department_id']);
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn(['preferred_locale', 'is_active', 'must_change_password']);
        });
    }
};
