<?php

use App\Http\Controllers\ColumnMessageController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\GenerationCancelController;
use App\Http\Controllers\GenerationRetryController;
use App\Http\Controllers\GenerationStreamController;
use App\Http\Controllers\SessionApiKeyController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/config', [ConfigController::class, 'show']);

Route::post('/session/openrouter-key', [SessionApiKeyController::class, 'store'])
    ->middleware('throttle:10,1'); // 10 attempts per minute
Route::delete('/session/openrouter-key', [SessionApiKeyController::class, 'destroy']);

Route::post('/workspaces', [WorkspaceController::class, 'store'])->middleware('web');
Route::get('/workspaces/{workspaceId}', [WorkspaceController::class, 'show'])->middleware(['web', 'session.owns']);

Route::post('/columns/{columnId}/messages', [ColumnMessageController::class, 'store'])->middleware(['web', 'session.owns']);

Route::get('/generations/{generationId}/stream', [GenerationStreamController::class, 'stream']);

Route::post('/generations/{generationId}/cancel', [GenerationCancelController::class, 'cancel'])->middleware(['web', 'session.owns']);

Route::post('/generations/{generationId}/retry', [GenerationRetryController::class, 'retry'])->middleware(['web', 'session.owns']);
