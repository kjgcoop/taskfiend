<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename projects.is_inbox -> projects.is_default
        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('is_inbox', 'is_default');
        });

        // Drop users.default_project_id (the is_default flag on projects replaces it)
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeignIdFor(\App\Models\Project::class, 'default_project_id');
            $table->dropColumn('default_project_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('default_project_id')->nullable()->constrained('projects')->nullOnDelete()->after('profile_image');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->renameColumn('is_default', 'is_inbox');
        });
    }
};
