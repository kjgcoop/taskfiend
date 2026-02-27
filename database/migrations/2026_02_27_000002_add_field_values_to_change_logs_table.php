<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('change_logs', function (Blueprint $table) {
            $table->string('field')->nullable()->after('verb');
            $table->text('old_value')->nullable()->after('field');
            $table->text('new_value')->nullable()->after('old_value');
        });
    }

    public function down(): void
    {
        Schema::table('change_logs', function (Blueprint $table) {
            $table->dropColumn(['field', 'old_value', 'new_value']);
        });
    }
};
