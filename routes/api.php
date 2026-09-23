<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BacklogController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\ChecklistItemController;
use App\Http\Controllers\Api\ChecklistReorderController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\SprintActionController;
use App\Http\Controllers\Api\SprintController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskReorderController;
use App\Http\Controllers\Api\TaskSprintController;
use App\Http\Controllers\Api\TaskStatusController;
use App\Http\Controllers\Api\TaskTagController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::apiResource('tags', TagController::class)->except(['show']);
    Route::apiResource('projects', ProjectController::class);
    Route::get('/projects/{project}/backlog', BacklogController::class)->name('projects.backlog');
    Route::get('/projects/{project}/board', BoardController::class)->name('projects.board');
    Route::patch('/projects/{project}/tasks/reorder', TaskReorderController::class)
        ->name('projects.tasks.reorder');
    Route::patch('/projects/{project}/tasks/{task}/sprint', TaskSprintController::class)
        ->scopeBindings()
        ->name('projects.tasks.sprint.update');
    Route::patch('/projects/{project}/tasks/{task}/status', TaskStatusController::class)
        ->scopeBindings()
        ->name('projects.tasks.status.update');
    Route::apiResource('projects.tasks', TaskController::class)->scoped();
    Route::post('/projects/{project}/sprints/{sprint}/start', [SprintActionController::class, 'start'])
        ->scopeBindings()
        ->name('projects.sprints.start');
    Route::post('/projects/{project}/sprints/{sprint}/complete', [SprintActionController::class, 'complete'])
        ->scopeBindings()
        ->name('projects.sprints.complete');
    Route::apiResource('projects.sprints', SprintController::class)->scoped();
    Route::put('/tasks/{task}/tags', TaskTagController::class)->name('tasks.tags.sync');
    Route::patch('/tasks/{task}/checklist/reorder', ChecklistReorderController::class)
        ->name('tasks.checklist.reorder');
    Route::post('/tasks/{task}/checklist', [ChecklistItemController::class, 'store'])
        ->name('tasks.checklist.store');
    Route::patch('/tasks/{task}/checklist/{checklistItem}', [ChecklistItemController::class, 'update'])
        ->scopeBindings()
        ->name('tasks.checklist.update');
    Route::delete('/tasks/{task}/checklist/{checklistItem}', [ChecklistItemController::class, 'destroy'])
        ->scopeBindings()
        ->name('tasks.checklist.destroy');
});
