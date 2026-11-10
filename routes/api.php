<?php

use App\Http\Controllers\Api\ChatAiController;
use App\Http\Controllers\Api\LoginController;
use Illuminate\Support\Facades\Route;

Route::post('login', LoginController::class)->middleware('throttle:5,1')->name('api.login');

Route::middleware(['auth:sanctum', 'throttle:30,1'])->group(function () {
    Route::post('chat-ai', [ChatAiController::class, 'store'])->name('api.chat-ai.store');
    Route::post('chat-ai/stream', [ChatAiController::class, 'stream'])->name('api.chat-ai.stream');
});
