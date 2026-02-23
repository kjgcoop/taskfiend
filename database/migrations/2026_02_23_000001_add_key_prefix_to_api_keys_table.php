<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            // Store the first 12 characters of the plaintext key for fast lookup.
            // This is not sensitive on its own — it narrows a request down to
            // (at most) a handful of candidate rows before we verify with Hash::check().
            $table->string('key_prefix', 12)->nullable()->after('key_hash');
            $table->index('key_prefix');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropIndex(['key_prefix']);
            $table->dropColumn('key_prefix');
        });
    }
};
