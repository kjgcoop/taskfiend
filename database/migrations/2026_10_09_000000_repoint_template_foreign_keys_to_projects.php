<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Templates become projects, so template_id on projects and scheduled_projects
 * now references projects.id. Existing rows still hold old project_templates
 * ids until `php artisan templates:convert-to-projects` repoints them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->foreign('template_id')->references('id')->on('projects')->nullOnDelete();
        });

        Schema::table('scheduled_projects', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->foreign('template_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->foreign('template_id')->references('id')->on('project_templates')->nullOnDelete();
        });

        Schema::table('scheduled_projects', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->foreign('template_id')->references('id')->on('project_templates')->cascadeOnDelete();
        });
    }
};
