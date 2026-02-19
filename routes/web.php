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

Route::get('/other-links', [OtherLinksController::class, 'index'])->name('other.links');
Route::get('/other-links/{title}', [OtherLinksController::class, 'show'])->name('other.links.link');

Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'day'])->name('dashboard');
    Route::get('/today', [DashboardController::class, 'day'])->name('today');
    Route::get('/overdue', [DashboardController::class, 'overdue'])->name('overdue');
    Route::get('/inbox', [DashboardController::class, 'inbox'])->name('inbox');
    Route::get('/all-tasks', [DashboardController::class, 'all'])->name('all-tasks');
    Route::get('/undated', [DashboardController::class, 'undated'])->name('undated');
    Route::get('/calendar', [DashboardController::class, 'calendar'])->name('calendar');
    Route::get('/day', [DashboardController::class, 'day'])->name('day');


    Route::resource('tasks', TaskController::class);
    Route::post('/tasks/{task}/update-field', [TaskController::class, 'updateField'])->name('tasks.updateField');
    Route::post('/tasks/parse-date', [TaskController::class, 'parseDate'])->name('tasks.parseDate');
    Route::post('/tasks/{task}/duplicate', [TaskController::class, 'duplicate'])->name('tasks.duplicate');

    Route::resource('projects', ProjectController::class)->except(['edit', 'update']);
    Route::post('/projects/{project}/update-field', [ProjectController::class, 'updateField'])->name('projects.updateField');
    Route::get('/projects/{project}/background', [ProjectController::class, 'showBackground'])->name('projects.background');
    Route::post('/projects/{project}/background', [ProjectController::class, 'uploadBackground'])->name('projects.background.upload');
    Route::delete('/projects/{project}/background', [ProjectController::class, 'removeBackground'])->name('projects.background.remove');
    Route::resource('tags', TagController::class);
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
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/profile-image/{user}', [ProfileController::class, 'showImage'])->name('profile.image.show');

    Route::get('/export', [DataExportController::class, 'exportAll'])->name('export.all');
    Route::post('/import', [DataExportController::class, 'importAll'])->name('import.all');

    Route::get('/projects/{project}/export-template', [DataExportController::class, 'exportProjectTemplate'])->name('projects.export-template');
    Route::post('/projects/import-template', [DataExportController::class, 'importProjectTemplate'])->name('projects.import-template');
});

require __DIR__.'/auth.php';
