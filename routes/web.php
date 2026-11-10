<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // The chat is the app's home; "dashboard" stays as the name Fortify redirects to after login.
    Route::redirect('dashboard', 'chat')->name('dashboard');
    Route::view('chat', 'chat')->name('chat');
});

require __DIR__.'/settings.php';
