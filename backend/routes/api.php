<?php

use App\Http\Controllers\SessionApiKeyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/session/openrouter-key', [SessionApiKeyController::class, 'store']);
Route::delete('/session/openrouter-key', [SessionApiKeyController::class, 'destroy']);
