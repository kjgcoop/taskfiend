<?php

use App\Http\Controllers\Api\TaskApiController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth.api', 'throttle:60,1'])->group(function () {
    Route::post('/tasks', [TaskApiController::class, 'create']);
    Route::get('/tasks/completed/{date}', [TaskApiController::class, 'completedOnDay']);
    Route::get('/tasks/on/{date}', [TaskApiController::class, 'onDay']);
});
