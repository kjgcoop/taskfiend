<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SQLite backfills the DEFAULT into every existing row when a column is added.
        Schema::table('projects', function (Blueprint $table) {
            $table->string('project_type')->default('normal')->after('status');
            $table->boolean('is_public')->default(false)->after('project_type');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['project_type', 'is_public']);
        });
    }
};
