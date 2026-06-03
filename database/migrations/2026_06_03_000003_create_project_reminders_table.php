<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('recurrence_pattern')->nullable();
            $table->boolean('recurrence_floating')->default(false);
            $table->boolean('dismissed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'date', 'dismissed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_reminders');
    }
};
