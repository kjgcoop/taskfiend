<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_logs', function (Blueprint $table) {
            // Composite index for the user activity view:
            // SELECT * FROM change_logs WHERE user_id = ? ORDER BY date DESC
            // Allows the DB to satisfy both the filter and the sort from a single index scan.
            $table->index(['user_id', 'date'], 'change_logs_user_id_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('change_logs', function (Blueprint $table) {
            $table->dropIndex('change_logs_user_id_date_index');
        });
    }
};
