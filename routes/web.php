<?php

use App\Livewire\Customers;
use App\Livewire\Packages;
use App\Livewire\Roles;
use App\Livewire\Routers\Create;
use App\Livewire\Routers\Edit;
use App\Livewire\Routers\Index;
use App\Livewire\Users;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::prefix('customers')->name('customers.')->group(function () {
        Route::middleware('permission:view_customers')->group(function () {
            Route::get('/', Customers\Index::class)->name('index');
        });
        Route::middleware('permission:manage_customers')->group(function () {
            Route::get('/create', Customers\Create::class)->name('create');
            Route::get('/{customer}/edit', Customers\Edit::class)->name('edit');
        });
        Route::middleware('permission:view_customers')->group(function () {
            Route::get('/{customer}', Customers\Show::class)->name('show');
        });
    });

    Route::prefix('packages')->name('packages.')->group(function () {
        Route::middleware('permission:view_packages')->group(function () {
            Route::get('/', Packages\Index::class)->name('index');
        });
    });

    Route::prefix('users')->name('users.')->group(function () {
        Route::middleware('permission:view_users')->group(function () {
            Route::get('/', Users\Index::class)->name('index');
        });
        Route::middleware('permission:manage_users')->group(function () {
            Route::get('/create', Users\Create::class)->name('create');
            Route::get('/{user}/edit', Users\Edit::class)->name('edit');
        });
    });

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::middleware('permission:manage_roles')->group(function () {
            Route::get('/', Roles\Index::class)->name('index');
            Route::get('/create', Roles\Create::class)->name('create');
            Route::get('/{role}/edit', Roles\Edit::class)->name('edit');
        });
    });

    Route::prefix('routers')->name('routers.')->group(function () {
        Route::middleware('permission:view_routers')->group(function () {
            Route::get('/', Index::class)->name('index');
        });
        Route::middleware('permission:manage_routers')->group(function () {
            Route::get('/create', Create::class)->name('create');
            Route::get('/{router}/edit', Edit::class)->name('edit');
        });
    });

    Route::prefix('ip-pools')->name('ip-pools.')->group(function () {
        Route::middleware('permission:view_ip_pools')->group(function () {
            Route::get('/', App\Livewire\IpPools\Index::class)->name('index');
        });
        Route::middleware('permission:manage_ip_pools')->group(function () {
            Route::get('/create', App\Livewire\IpPools\Create::class)->name('create');
            Route::get('/{pool}/edit', App\Livewire\IpPools\Edit::class)->name('edit');
        });
    });
});

require __DIR__.'/settings.php';
