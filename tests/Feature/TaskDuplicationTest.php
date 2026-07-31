<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Characterization tests for the three "copy a task tree" call paths:
 *   - Task::duplicate()                  (manual "Duplicate" button, single task)
 *   - Project::duplicate()               (manual project duplicate, incomplete tasks only)
 *   - TaskLifecycle recurring rollover   (createNextOccurrence + subtask copy)
 *
 * These lock in the ownership/status/field-copying semantics that must survive
 * consolidating the three implementations. The one behavior deliberately NOT
 * pinned here is attachment file-sharing on the recurring path — consolidating
 * onto Task::duplicate()'s physical-copy behavior is an intentional bug fix
 * (previously, deleting an attachment from one occurrence deleted the
 * underlying file out from under every other occurrence sharing its path).
 */
class TaskDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private User $assignee;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->creator  = User::factory()->create();
        $this->assignee = User::factory()->create();

        $this->project = Project::create([
            'name'    => 'Duplication Project',
            'user_id' => $this->creator->id,
            'status'  => 'incomplete',
        ]);
    }

    private function makeTask(array $attributes = []): Task
    {
        $task = Task::create(array_merge([
            'name'       => 'Original task',
            'creator_id' => $this->creator->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ], $attributes));

        // Route-model-bound tasks are always fetched fresh from the DB, so
        // column defaults (e.g. recurrence_floating) are already applied —
        // mirror that here instead of leaving in-memory attributes null.
        return $task->refresh();
    }

    private function attachFile(Task $task, User $uploader): TaskAttachment
    {
        $path = Storage::disk('private')->putFile('task_attachments', UploadedFile::fake()->create('doc.pdf', 10));

        return $task->attachments()->create([
            'user_id'           => $uploader->id,
            'file_path'         => $path,
            'original_filename' => 'doc.pdf',
            'mime_type'         => 'application/pdf',
            'file_size'         => 10240,
        ]);
    }

    // ── Task::duplicate() — manual "Duplicate" button ──────────────────────

    public function test_manual_duplicate_attributes_copy_to_current_user(): void
    {
        $task = $this->makeTask();
        $task->assignments()->create(['assignee_id' => $this->creator->id, 'assigned_by_id' => $this->creator->id]);

        $this->actingAs($this->assignee);
        $copy = $task->duplicate(['name' => 'Copy']);

        // The person clicking "Duplicate" becomes the creator of the copy,
        // regardless of who created (or is assigned to) the original.
        $this->assertSame($this->assignee->id, $copy->creator_id);
    }

    public function test_manual_duplicate_falls_back_to_current_user_when_source_has_no_assignees(): void
    {
        $task = $this->makeTask();

        $this->actingAs($this->assignee);
        $copy = $task->duplicate(['name' => 'Copy']);

        $this->assertSame([$this->assignee->id], $copy->assignees->pluck('id')->all());
    }

    public function test_manual_duplicate_reassigns_by_current_user(): void
    {
        $task = $this->makeTask();
        $task->assignments()->create(['assignee_id' => $this->assignee->id, 'assigned_by_id' => $this->creator->id]);

        $this->actingAs($this->creator);
        $copy = $task->duplicate(['name' => 'Copy']);

        // The assignee carries over, but "assigned by" reflects whoever
        // clicked Duplicate now, not the original assignment's assigner.
        $assignment = $copy->assignments->first();
        $this->assertSame($this->assignee->id, $assignment->assignee_id);
        $this->assertSame($this->creator->id, $assignment->assigned_by_id);
    }

    public function test_manual_duplicate_does_not_copy_subtasks(): void
    {
        $task  = $this->makeTask();
        $child = $this->makeTask(['name' => 'Child', 'parent_id' => $task->id]);

        $this->actingAs($this->creator);
        $copy = $task->duplicate(['name' => 'Copy']);

        $this->assertSame(0, $copy->children()->count());
    }

    public function test_manual_duplicate_copies_tags(): void
    {
        $tag  = Tag::create(['tag_name' => 'urgent', 'color' => '#ff0000']);
        $task = $this->makeTask();
        $task->tags()->sync([$tag->id]);

        $this->actingAs($this->creator);
        $copy = $task->duplicate(['name' => 'Copy']);

        $this->assertSame([$tag->id], $copy->tags->pluck('id')->all());
    }

    public function test_manual_duplicate_physically_copies_attachment_file(): void
    {
        $task       = $this->makeTask();
        $attachment = $this->attachFile($task, $this->creator);

        $this->actingAs($this->creator);
        $copy = $task->duplicate(['name' => 'Copy']);

        $copyAttachment = $copy->attachments->first();
        $this->assertNotSame($attachment->file_path, $copyAttachment->file_path);
        Storage::disk('private')->assertExists($copyAttachment->file_path);
    }

    public function test_manual_duplicate_status_is_always_incomplete(): void
    {
        $task = $this->makeTask(['status' => 'done', 'completed_at' => now()]);

        $this->actingAs($this->creator);
        $copy = $task->duplicate(['name' => 'Copy']);

        $this->assertSame('incomplete', $copy->status);
        $this->assertNull($copy->completed_at);
    }

    // ── Project::duplicate() ────────────────────────────────────────────────

    public function test_project_duplicate_only_copies_incomplete_root_tasks(): void
    {
        $this->makeTask(['name' => 'Open task']);
        $this->makeTask(['name' => 'Done task', 'status' => 'done']);

        $this->actingAs($this->creator);
        $newProject = $this->project->duplicate();

        $names = $newProject->tasks()->pluck('name')->all();
        $this->assertSame(['Open task'], $names);
    }

    public function test_project_duplicate_only_copies_incomplete_subtasks(): void
    {
        $parent = $this->makeTask(['name' => 'Parent']);
        $this->makeTask(['name' => 'Open child', 'parent_id' => $parent->id]);
        $this->makeTask(['name' => 'Done child', 'parent_id' => $parent->id, 'status' => 'done']);

        $this->actingAs($this->creator);
        $newProject = $this->project->duplicate();

        $newParent = $newProject->tasks()->where('name', 'Parent')->first();
        $this->assertSame(['Open child'], $newParent->children()->pluck('name')->all());
    }

    public function test_project_duplicate_moves_all_nested_tasks_into_new_project(): void
    {
        $parent = $this->makeTask(['name' => 'Parent']);
        $child  = $this->makeTask(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->actingAs($this->creator);
        $newProject = $this->project->duplicate();

        $newParent = $newProject->tasks()->where('name', 'Parent')->first();
        $newChild  = $newParent->children()->first();
        $this->assertSame($newProject->id, $newParent->project_id);
        $this->assertSame($newProject->id, $newChild->project_id);
    }

    public function test_project_duplicate_reassigns_tasks_to_current_user(): void
    {
        $this->makeTask(['name' => 'Task']);

        $this->actingAs($this->assignee);
        $newProject = $this->project->duplicate();

        $newTask = $newProject->tasks()->first();
        $this->assertSame($this->assignee->id, $newTask->creator_id);
    }

    // ── Recurring rollover ──────────────────────────────────────────────────

    private function updateField(Task $task, string $field, $value, array $extra = [])
    {
        return $this->actingAs($this->creator)
            ->post(route('tasks.updateField', $task), array_merge([
                'field' => $field,
                'value' => $value,
            ], $extra));
    }

    public function test_recurring_rollover_preserves_original_creator_not_completer(): void
    {
        $task = $this->makeTask([
            'name'               => 'Daily standup',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'daily',
        ]);
        $task->assignments()->create(['assignee_id' => $this->creator->id, 'assigned_by_id' => $this->creator->id]);

        // A different user (an assignee, not the creator) completes the task.
        $this->actingAs($this->assignee)->post(route('tasks.updateField', $task), [
            'field' => 'status',
            'value' => 'done',
        ]);

        $next = Task::where('name', 'Daily standup')->where('status', 'incomplete')->first();

        // The next occurrence still belongs to the original creator, not
        // whoever happened to complete this instance.
        $this->assertSame($this->creator->id, $next->creator_id);
    }

    public function test_recurring_rollover_preserves_original_assignment_metadata(): void
    {
        $task = $this->makeTask([
            'name'               => 'Daily standup',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'daily',
        ]);
        $task->assignments()->create(['assignee_id' => $this->assignee->id, 'assigned_by_id' => $this->creator->id]);

        $this->updateField($task, 'status', 'done');

        $next = Task::where('name', 'Daily standup')->where('status', 'incomplete')->first();
        $assignment = $next->assignments->first();

        $this->assertSame($this->assignee->id, $assignment->assignee_id);
        $this->assertSame($this->creator->id, $assignment->assigned_by_id);
    }

    public function test_recurring_rollover_copies_all_subtasks_regardless_of_status(): void
    {
        $task = $this->makeTask([
            'name'               => 'Weekly review',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'weekly',
        ]);
        $this->makeTask(['name' => 'Open subtask', 'parent_id' => $task->id]);
        $this->makeTask(['name' => 'Done subtask', 'parent_id' => $task->id, 'status' => 'done']);

        $this->updateField($task, 'status', 'done');

        $next = Task::where('name', 'Weekly review')->where('status', 'incomplete')->first();
        $childNames = $next->children()->pluck('name')->sort()->values()->all();

        // Unlike project duplication, recurring rollover carries over every
        // subtask regardless of its current status.
        $this->assertSame(['Done subtask', 'Open subtask'], $childNames);
    }

    public function test_recurring_rollover_subtask_creator_is_own_original_creator(): void
    {
        $task  = $this->makeTask([
            'name'               => 'Weekly review',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'weekly',
        ]);
        $child = $this->makeTask(['name' => 'Subtask', 'parent_id' => $task->id, 'creator_id' => $this->assignee->id]);

        $this->updateField($task, 'status', 'done');

        $next     = Task::where('name', 'Weekly review')->where('status', 'incomplete')->first();
        $newChild = $next->children()->first();

        $this->assertSame($this->assignee->id, $newChild->creator_id);
    }

    public function test_recurring_rollover_subtasks_do_not_get_a_recurrence_pattern(): void
    {
        $task  = $this->makeTask([
            'name'               => 'Weekly review',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'weekly',
        ]);
        $this->makeTask(['name' => 'Subtask', 'parent_id' => $task->id]);

        $this->updateField($task, 'status', 'done');

        $next     = Task::where('name', 'Weekly review')->where('status', 'incomplete')->first();
        $newChild = $next->children()->first();

        $this->assertNull($newChild->recurrence_pattern);
    }

    public function test_recurring_rollover_logs_a_change_for_each_subtask(): void
    {
        $task = $this->makeTask([
            'name'               => 'Weekly review',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'weekly',
        ]);
        $this->makeTask(['name' => 'Subtask', 'parent_id' => $task->id]);

        $this->updateField($task, 'status', 'done');

        $next     = Task::where('name', 'Weekly review')->where('status', 'incomplete')->first();
        $newChild = $next->children()->first();

        $this->assertTrue(
            $newChild->changeLogs()
                ->where('description', 'created subtask from recurring parent')
                ->exists()
        );
        $this->assertTrue(
            $next->changeLogs()
                ->where('description', 'created recurring task')
                ->exists()
        );
    }

    public function test_recurring_rollover_root_attachment_is_physically_copied_not_shared(): void
    {
        $task = $this->makeTask([
            'name'               => 'Daily standup',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'daily',
        ]);
        $original = $this->attachFile($task, $this->creator);

        $this->updateField($task, 'status', 'done');

        $next           = Task::where('name', 'Daily standup')->where('status', 'incomplete')->first();
        $nextAttachment = $next->attachments->first();

        // Fixed behavior: each occurrence owns its own copy of the file, so
        // deleting an attachment from one instance can never remove the file
        // out from under another instance that references the same upload.
        $this->assertNotSame($original->file_path, $nextAttachment->file_path);
        Storage::disk('private')->assertExists($nextAttachment->file_path);
        Storage::disk('private')->assertExists($original->file_path);
    }
}
