<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('completed_at');
        });

        // Back-fill for already-archived tasks using the change_logs table
        DB::statement("
            UPDATE tasks
            SET archived_at = (
                SELECT created_at
                FROM change_logs
                WHERE entity_type = 'tasks'
                  AND entity_id   = tasks.id
                  AND field       = 'status'
                  AND new_value   = 'archived'
                ORDER BY created_at DESC
                LIMIT 1
            )
            WHERE status = 'archived'
        ");
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('archived_at');
        });
    }
};
