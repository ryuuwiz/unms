<?php

use App\Http\Controllers\InvoicePdfController;
use App\Livewire\Invoice;
use App\Livewire\IpPool;
use App\Livewire\Laporan;
use App\Livewire\LayananPelanggan;
use App\Livewire\PaketLayanan;
use App\Livewire\Pelanggan;
use App\Livewire\Pembayaran;
use App\Livewire\ProfilBandwidth;
use App\Livewire\Promo;
use App\Livewire\Roles;
use App\Livewire\Router;
use App\Livewire\Users;
use App\Livewire\Wilayah;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::view('dashboard', 'dashboard')->name('dashboard');

    // ─── Pelanggan ───────────────────────────────────────────────
    Route::prefix('pelanggan')->name('pelanggan.')->group(function () {
        Route::middleware('permission:pelanggan.buat')->group(function () {
            Route::get('/create', Pelanggan\Create::class)->name('create');
        });
        Route::middleware('permission:pelanggan.ubah')->group(function () {
            Route::get('/{pelanggan}/edit', Pelanggan\Edit::class)->name('edit');
        });
        Route::middleware('permission:pelanggan.lihat')->group(function () {
            Route::get('/', Pelanggan\Index::class)->name('index');
            Route::get('/{pelanggan}', Pelanggan\Show::class)->name('show');
        });
    });

    // ─── Layanan Pelanggan ────────────────────────────────────────
    Route::prefix('layanan-pelanggan')->name('layanan-pelanggan.')->group(function () {
        Route::middleware('permission:layanan_pelanggan.buat')->group(function () {
            Route::get('/create', LayananPelanggan\Create::class)->name('create');
        });
        Route::middleware('permission:layanan_pelanggan.ubah')->group(function () {
            Route::get('/{layananPelanggan}/edit', LayananPelanggan\Edit::class)->name('edit');
        });
        Route::middleware('permission:layanan_pelanggan.lihat')->group(function () {
            Route::get('/', LayananPelanggan\Index::class)->name('index');
        });
    });

    // ─── Paket Layanan ────────────────────────────────────────────
    Route::prefix('paket-layanan')->name('paket-layanan.')->group(function () {
        Route::middleware('permission:paket_layanan.buat')->group(function () {
            Route::get('/create', PaketLayanan\Create::class)->name('create');
        });
        Route::middleware('permission:paket_layanan.ubah')->group(function () {
            Route::get('/{paketLayanan}/edit', PaketLayanan\Edit::class)->name('edit');
        });
        Route::middleware('permission:paket_layanan.lihat')->group(function () {
            Route::get('/', PaketLayanan\Index::class)->name('index');
        });
    });

    // ─── Profil Bandwidth ─────────────────────────────────────────
    Route::prefix('profil-bandwidth')->name('profil-bandwidth.')->group(function () {
        Route::middleware('permission:profil_bandwidth.buat')->group(function () {
            Route::get('/create', ProfilBandwidth\Create::class)->name('create');
        });
        Route::middleware('permission:profil_bandwidth.ubah')->group(function () {
            Route::get('/{profilBandwidth}/edit', ProfilBandwidth\Edit::class)->name('edit');
        });
        Route::middleware('permission:profil_bandwidth.lihat')->group(function () {
            Route::get('/', ProfilBandwidth\Index::class)->name('index');
        });
    });

    // ─── Invoice & Tagihan ────────────────────────────────────────
    Route::prefix('invoice')->name('invoice.')->group(function () {
        Route::middleware('permission:invoice.buat')->group(function () {
            Route::get('/create', Invoice\Create::class)->name('create');
        });
        Route::middleware('permission:invoice.cetak')->group(function () {
            Route::get('/{invoice}/cetak', [InvoicePdfController::class, 'cetak'])->name('cetak');
        });
        Route::middleware('permission:invoice.lihat')->group(function () {
            Route::get('/', Invoice\Index::class)->name('index');
            Route::get('/{invoice}', Invoice\Show::class)->name('show');
        });
    });

    // ─── Pembayaran ───────────────────────────────────────────────
    Route::prefix('pembayaran')->name('pembayaran.')->group(function () {
        Route::middleware('permission:pembayaran.lihat')->group(function () {
            Route::get('/', Pembayaran\Index::class)->name('index');
        });
    });

    // ─── Promo & Diskon ───────────────────────────────────────────
    Route::prefix('promo')->name('promo.')->group(function () {
        Route::middleware('permission:promo.buat')->group(function () {
            Route::get('/create', Promo\Create::class)->name('create');
        });
        Route::middleware('permission:promo.ubah')->group(function () {
            Route::get('/{promo}/edit', Promo\Edit::class)->name('edit');
        });
        Route::middleware('permission:promo.lihat')->group(function () {
            Route::get('/', Promo\Index::class)->name('index');
        });
    });

    // ─── Laporan Keuangan ─────────────────────────────────────────
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::middleware('permission:laporan.lihat')->group(function () {
            Route::get('/billing', Laporan\Billing::class)->name('billing');
        });
    });

    // ─── Router ───────────────────────────────────────────────────
    Route::prefix('router')->name('router.')->group(function () {
        Route::middleware('permission:router.buat')->group(function () {
            Route::get('/create', Router\Create::class)->name('create');
        });
        Route::middleware('permission:router.ubah')->group(function () {
            Route::get('/{router}/edit', Router\Edit::class)->name('edit');
        });
        Route::middleware('permission:router.lihat')->group(function () {
            Route::get('/', Router\Index::class)->name('index');
        });
    });

    // ─── IP Pool ──────────────────────────────────────────────────
    Route::prefix('ip-pool')->name('ip-pool.')->group(function () {
        Route::middleware('permission:ip_pool.buat')->group(function () {
            Route::get('/create', IpPool\Create::class)->name('create');
        });
        Route::middleware('permission:ip_pool.ubah')->group(function () {
            Route::get('/{pool}/edit', IpPool\Edit::class)->name('edit');
        });
        Route::middleware('permission:ip_pool.lihat')->group(function () {
            Route::get('/', IpPool\Index::class)->name('index');
        });
    });

    // ─── Wilayah ──────────────────────────────────────────────────
    Route::prefix('wilayah')->name('wilayah.')->group(function () {
        Route::middleware('permission:wilayah.buat')->group(function () {
            Route::get('/kota/create', Wilayah\Kota\Create::class)->name('kota.create');
            Route::get('/kecamatan/create', Wilayah\Kecamatan\Create::class)->name('kecamatan.create');
            Route::get('/kelurahan/create', Wilayah\Kelurahan\Create::class)->name('kelurahan.create');
            Route::get('/perumahan/create', Wilayah\Perumahan\Create::class)->name('perumahan.create');
        });
        Route::middleware('permission:wilayah.ubah')->group(function () {
            Route::get('/kota/{kota}/edit', Wilayah\Kota\Edit::class)->name('kota.edit');
            Route::get('/kecamatan/{kecamatan}/edit', Wilayah\Kecamatan\Edit::class)->name('kecamatan.edit');
            Route::get('/kelurahan/{kelurahan}/edit', Wilayah\Kelurahan\Edit::class)->name('kelurahan.edit');
            Route::get('/perumahan/{perumahan}/edit', Wilayah\Perumahan\Edit::class)->name('perumahan.edit');
        });
        Route::middleware('permission:wilayah.lihat')->group(function () {
            Route::get('/kota', Wilayah\Kota\Index::class)->name('kota.index');
            Route::get('/kecamatan', Wilayah\Kecamatan\Index::class)->name('kecamatan.index');
            Route::get('/kelurahan', Wilayah\Kelurahan\Index::class)->name('kelurahan.index');
            Route::get('/perumahan', Wilayah\Perumahan\Index::class)->name('perumahan.index');
        });
    });

    // ─── Pengguna & Peran ─────────────────────────────────────────
    Route::prefix('users')->name('users.')->group(function () {
        Route::middleware('permission:pengguna.buat')->group(function () {
            Route::get('/create', Users\Create::class)->name('create');
        });
        Route::middleware('permission:pengguna.ubah')->group(function () {
            Route::get('/{user}/edit', Users\Edit::class)->name('edit');
        });
        Route::middleware('permission:pengguna.lihat')->group(function () {
            Route::get('/', Users\Index::class)->name('index');
        });
    });

    Route::prefix('roles')->name('roles.')->group(function () {
        Route::middleware('permission:peran.buat')->group(function () {
            Route::get('/create', Roles\Create::class)->name('create');
        });
        Route::middleware('permission:peran.ubah')->group(function () {
            Route::get('/{role}/edit', Roles\Edit::class)->name('edit');
        });
        Route::middleware('permission:peran.lihat')->group(function () {
            Route::get('/', Roles\Index::class)->name('index');
        });
    });
});

require __DIR__.'/settings.php';
