<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();   // recipient
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete(); // who acted
            $table->string('actor_name');                                      // denormalized
            $table->foreignId('change_log_id')->nullable()->constrained('change_logs')->nullOnDelete();
            $table->string('entity_type');                                     // 'tasks' | 'projects'
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_name');                                     // denormalized
            $table->text('description');                                       // e.g. "updated status to done"
            $table->boolean('seen')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'seen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
