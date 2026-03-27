<?php

use App\Http\Controllers\ChangeLogController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DataExportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TaskAttachmentController;
use App\Http\Controllers\TaskController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OtherLinksController;
use App\Http\Controllers\ProjectTemplateController;

Route::middleware('auth')->group(function () {
    Route::get('/other-links', [OtherLinksController::class, 'index'])->name('other.links');
    Route::get('/other-links/{path}', [OtherLinksController::class, 'show'])->name('other.links.link')->where('path', '.+');

    Route::get('/', [DashboardController::class, 'day'])->name('dashboard');
    Route::get('/today', [DashboardController::class, 'day'])->name('today');
    Route::get('/overdue', [DashboardController::class, 'overdue'])->name('overdue');
    Route::get('/inbox', [DashboardController::class, 'inbox'])->name('inbox');
    Route::get('/all-tasks', [DashboardController::class, 'all'])->name('all-tasks');
    Route::get('/undated', [DashboardController::class, 'undated'])->name('undated');
    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');
    Route::get('/day', [DashboardController::class, 'day'])->name('day');
    Route::get('/day/export-markdown', [DashboardController::class, 'exportDayMarkdown'])->name('day.export-markdown');


    Route::resource('tasks', TaskController::class);
    Route::get('/tasks/{task}/panel', [TaskController::class, 'panel'])->name('tasks.panel');
    Route::post('/tasks/{task}/update-field', [TaskController::class, 'updateField'])->name('tasks.updateField');
    Route::post('/tasks/parse-date', [TaskController::class, 'parseDate'])->name('tasks.parseDate');
    Route::post('/tasks/preview-quick-add', [TaskController::class, 'previewQuickAdd'])->name('tasks.previewQuickAdd');
    Route::post('/tasks/bulk-update', [TaskController::class, 'bulkUpdate'])->name('tasks.bulkUpdate');
    Route::post('/tasks/reorder', [TaskController::class, 'reorder'])->name('tasks.reorder');
    Route::post('/tasks/{task}/duplicate', [TaskController::class, 'duplicate'])->name('tasks.duplicate');

    Route::resource('projects', ProjectController::class)->except(['edit', 'update']);
    Route::get('/projects/{project}/completed-tasks', [ProjectController::class, 'completedTasks'])->name('projects.completedTasks');
    Route::get('/projects/{project}/archived-tasks', [ProjectController::class, 'archivedTasks'])->name('projects.archivedTasks');
    Route::post('/projects/{project}/update-field', [ProjectController::class, 'updateField'])->name('projects.updateField');
    Route::get('/projects/{project}/background', [ProjectController::class, 'showBackground'])->name('projects.background');
    Route::post('/projects/{project}/background', [ProjectController::class, 'uploadBackground'])->name('projects.background.upload');
    Route::delete('/projects/{project}/background', [ProjectController::class, 'removeBackground'])->name('projects.background.remove');
    Route::resource('tags', TagController::class)->except(['edit', 'update']);
    Route::post('/tags/{tag}/update-field', [TagController::class, 'updateField'])->name('tags.updateField');
    Route::get('/tags/{tag}/completed-tasks', [TagController::class, 'completedTasks'])->name('tags.completedTasks');
    Route::get('/tags/{tag}/archived-tasks', [TagController::class, 'archivedTasks'])->name('tags.archivedTasks');
    Route::post('/tags/quick-create', [TagController::class, 'quickStore'])->name('tags.quickStore');

    Route::post('/tasks/{task}/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::delete('/tasks/{task}/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::get('/tasks/{task}/comments/{comment}/download', [CommentController::class, 'download'])->name('comments.download');

    Route::post('/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('attachments.destroy');
    Route::get('/tasks/{task}/attachments/{attachment}/download', [TaskAttachmentController::class, 'download'])->name('attachments.download');
    Route::get('/tasks/{task}/attachments/{attachment}/view', [TaskAttachmentController::class, 'view'])->name('attachments.view');

    Route::get('/search', [SearchController::class, 'index'])->name('search');

    Route::get('/changelogs/task/{task}', [ChangeLogController::class, 'task'])->name('changelogs.task');
    Route::get('/changelogs/project/{project}', [ChangeLogController::class, 'project'])->name('changelogs.project');
    Route::get('/changelogs/tag/{tag}', [ChangeLogController::class, 'tag'])->name('changelogs.tag');
    Route::get('/changelogs/user', [ChangeLogController::class, 'user'])->name('changelogs.user');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/image', [ProfileController::class, 'updateImage'])->name('profile.image.update');
    Route::delete('/profile/image', [ProfileController::class, 'destroyImage'])->name('profile.image.destroy');
    Route::delete('/profile/sessions', [ProfileController::class, 'destroySessions'])->name('profile.sessions.destroy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile-image/{user}', [ProfileController::class, 'showImage'])->name('profile.image.show');

    Route::get('/templates', [ProjectTemplateController::class, 'index'])->name('templates.index');
    Route::post('/projects/{project}/save-as-template', [ProjectTemplateController::class, 'store'])->name('templates.store');
    Route::post('/templates/{template}/create-project', [ProjectTemplateController::class, 'createFromTemplate'])->name('templates.createFromTemplate');
    Route::delete('/templates/{template}', [ProjectTemplateController::class, 'destroy'])->name('templates.destroy');

    Route::get('/export', [DataExportController::class, 'exportAll'])->name('export.all');
    // TODO: importAll is disabled — the ID-based upsert has no ownership checks, so importing
    // into an account that already has data (e.g. a shared server) can silently overwrite
    // other users' records. Needs ID remapping before it's safe to re-enable.
    // Route::post('/import', [DataExportController::class, 'importAll'])->name('import.all');

    Route::get('/projects/{project}/export-template', [DataExportController::class, 'exportProjectTemplate'])->name('projects.export-template');
    Route::get('/projects/{project}/export-markdown', [DataExportController::class, 'exportMarkdown'])->name('projects.export-markdown');
    Route::post('/projects/import-template', [DataExportController::class, 'importProjectTemplate'])->name('projects.import-template');
});

// Session validity check — used by the client-side polling heartbeat.
// Returns 200 {"ok":true} when authenticated, 401 {"ok":false} otherwise.
// Must be outside the auth middleware group so it returns JSON instead of redirecting.
Route::get('/auth/check', function () {
    return auth()->check()
        ? response()->json(['ok' => true])
        : response()->json(['ok' => false], 401);
})->name('auth.check');

require __DIR__.'/auth.php';
