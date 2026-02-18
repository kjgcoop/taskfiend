<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coerce any existing null descriptions to empty string before removing nullable
        DB::table('tasks')->whereNull('description')->update(['description' => '']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->text('description')->default('')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->text('description')->nullable()->default(null)->change();
        });
    }
};
