<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_reminders', function (Blueprint $table) {
            $table->string('note', 255)->nullable()->after('recurrence_floating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_reminders', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
