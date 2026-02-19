<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Backfill any tasks with a null project_id to their creator's inbox project.
        // Tasks without a project should have been assigned to inbox on creation; this
        // handles any legacy rows that slipped through.
        $nullTasks = DB::table('tasks')
            ->whereNull('project_id')
            ->select('id', 'creator_id')
            ->get();

        if ($nullTasks->isNotEmpty()) {
            $creatorIds = $nullTasks->pluck('creator_id')->unique();

            $inboxProjects = DB::table('projects')
                ->whereIn('user_id', $creatorIds)
                ->where('is_inbox', true)
                ->get(['id', 'user_id'])
                ->keyBy('user_id');

            foreach ($nullTasks as $task) {
                $inbox = $inboxProjects[$task->creator_id] ?? null;

                if (!$inbox) {
                    // Create an inbox project for this user if one doesn't exist yet.
                    $inboxId = DB::table('projects')->insertGetId([
                        'name'       => 'Inbox',
                        'user_id'    => $task->creator_id,
                        'is_inbox'   => true,
                        'status'     => 'incomplete',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $inbox = (object) ['id' => $inboxId, 'user_id' => $task->creator_id];
                    $inboxProjects[$task->creator_id] = $inbox;
                }

                DB::table('tasks')
                    ->where('id', $task->id)
                    ->update(['project_id' => $inbox->id]);
            }
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->change();
        });
    }
};
