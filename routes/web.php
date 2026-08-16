<?php

use App\Livewire\Roles;
use App\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::middleware('permission:manage_users')
        ->prefix('users')
        ->name('users.')
        ->group(function () {
            Route::get('/', Users\Index::class)->name('index');
            Route::get('/create', Users\Create::class)->name('create');
            Route::get('/{user}/edit', Users\Edit::class)->name('edit');
        });

    Route::middleware('role:super_admin')
        ->prefix('roles')
        ->name('roles.')
        ->group(function () {
            Route::get('/', Roles\Index::class)->name('index');
            Route::get('/create', Roles\Create::class)->name('create');
            Route::get('/{role}/edit', Roles\Edit::class)->name('edit');
        });
});

require __DIR__.'/settings.php';
