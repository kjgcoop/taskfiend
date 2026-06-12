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

        // Drop users.default_project_id (the is_default flag on projects replaces it).
        // SQLite cannot drop a column that appears in a FK constraint; we rebuild the table.
        Schema::withoutForeignKeyConstraints(function () {
            DB::statement('
                CREATE TABLE "users_new" (
                    "id" integer primary key autoincrement not null,
                    "email" varchar not null,
                    "name" varchar not null,
                    "password" varchar not null,
                    "email_enabled_at" datetime null,
                    "email_verified_at" datetime null,
                    "remember_token" varchar(100) null,
                    "profile_image" varchar null,
                    "created_at" datetime null,
                    "updated_at" datetime null
                )
            ');
            DB::statement('INSERT INTO users_new SELECT id, email, name, password, email_enabled_at, email_verified_at, remember_token, profile_image, created_at, updated_at FROM users');
            DB::statement('DROP TABLE users');
            DB::statement('ALTER TABLE users_new RENAME TO users');
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');
            DB::statement('CREATE INDEX users_email_index ON users (email)');
            DB::statement('CREATE INDEX users_email_enabled_at_index ON users (email_enabled_at)');
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
