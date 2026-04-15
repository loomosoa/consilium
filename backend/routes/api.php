<?php

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\SessionApiKeyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/config', [ConfigController::class, 'show']);

Route::post('/session/openrouter-key', [SessionApiKeyController::class, 'store'])
    ->middleware('throttle:10,1'); // 10 attempts per minute
Route::delete('/session/openrouter-key', [SessionApiKeyController::class, 'destroy']);
