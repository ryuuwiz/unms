<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::get('users', \App\Livewire\UsersList::class)->name('users.index');
});

require __DIR__.'/settings.php';
