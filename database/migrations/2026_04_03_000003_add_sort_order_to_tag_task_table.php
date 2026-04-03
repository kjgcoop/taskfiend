<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tag_task', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('tag_id');
        });
    }

    public function down(): void
    {
        Schema::table('tag_task', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
