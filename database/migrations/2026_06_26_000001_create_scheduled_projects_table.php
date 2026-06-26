<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('project_templates')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('project_name');
            $table->date('start_date');
            $table->boolean('is_created')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'start_date', 'is_created']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_projects');
    }
};
