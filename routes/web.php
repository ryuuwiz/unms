<?php

use App\Http\Controllers\Api\MapMarkerController;
use App\Http\Controllers\BarangLabelController;
use App\Http\Controllers\ImpersonateController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\PelangganMediaController;
use App\Http\Controllers\Webhook\PaymentWebhookController;
use App\Http\Controllers\Webhook\WhatsappWebhookController;
use App\Http\Controllers\Webhook\XenditWebhookController;
use App\Livewire\Barang;
use App\Livewire\BarangKeluar;
use App\Livewire\BarangMasuk;
use App\Livewire\Invoice;
use App\Livewire\IpPool;
use App\Livewire\IpPublik;
use App\Livewire\Laporan;
use App\Livewire\LayananPelanggan;
use App\Livewire\Maps\EstimasiKabel;
use App\Livewire\Maps\Lokasi;
use App\Livewire\MediaLibrary;
use App\Livewire\Odp\Create;
use App\Livewire\Odp\Edit;
use App\Livewire\PaketLayanan;
use App\Livewire\Pelanggan;
use App\Livewire\Pembayaran;
use App\Livewire\Portal\Auth\GantiPassword;
use App\Livewire\Portal\Auth\KlaimAkun;
use App\Livewire\Portal\Auth\Login;
use App\Livewire\Portal\Dashboard;
use App\Livewire\Portal\Invoice\Index;
use App\Livewire\Portal\Invoice\Show;
use App\Livewire\ProfilBandwidth;
use App\Livewire\Promo;
use App\Livewire\Roles;
use App\Livewire\Router;
use App\Livewire\Settings\PengaturanCabangBarang;
use App\Livewire\Settings\PengaturanGateway;
use App\Livewire\Settings\PengaturanJenisBarang;
use App\Livewire\Settings\PengaturanKondisiBarang;
use App\Livewire\Settings\PengaturanPrefixRegistrasi;
use App\Livewire\Settings\TemplateDeskripsiTagihan;
use App\Livewire\Settings\WhatsappSettings;
use App\Livewire\Ticket;
use App\Livewire\Users;
use App\Livewire\Wilayah;
use App\Models\Perusahaan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// ─── Dynamic Favicon Routes (Company Logo) ────────────────────────
Route::get('/favicon.ico', function () {
    $perusahaan = Perusahaan::default();
    $media = $perusahaan->getFirstMedia('logo');
    $content = $perusahaan->getLogoContent();
    if ($media && $content !== null) {
        return response($content, 200, [
            'Content-Type' => $media->mime_type ?: 'image/x-icon',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    return response(Perusahaan::defaultGobillingSvg(), 200, [
        'Content-Type' => 'image/svg+xml',
    ]);
});

Route::get('/favicon.svg', function () {
    $perusahaan = Perusahaan::default();
    $media = $perusahaan->getFirstMedia('logo');
    $content = $perusahaan->getLogoContent();
    if ($media && $content !== null) {
        return response($content, 200, [
            'Content-Type' => $media->mime_type ?: 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    return response(Perusahaan::defaultGobillingSvg(), 200, [
        'Content-Type' => 'image/svg+xml',
    ]);
});

Route::get('/apple-touch-icon.png', function () {
    $perusahaan = Perusahaan::default();
    $media = $perusahaan->getFirstMedia('logo');
    $content = $perusahaan->getLogoContent();
    if ($media && $content !== null) {
        return response($content, 200, [
            'Content-Type' => $media->mime_type ?: 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    return response(Perusahaan::defaultGobillingSvg(), 200, [
        'Content-Type' => 'image/svg+xml',
    ]);
});

Route::redirect('/', 'login')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::get('dashboard', App\Livewire\Dashboard::class)->name('dashboard');

    // ─── Tiket & Operasional ──────────────────────────────────────
    Route::prefix('ticket')->name('ticket.')->group(function () {
        Route::middleware('permission:ticket.buat')->group(function () {
            Route::get('/create', Ticket\Create::class)->name('create');
        });
        Route::middleware('permission:ticket.lihat')->group(function () {
            Route::get('/', Ticket\Index::class)->name('index');
            Route::get('/{ticket}', Ticket\Show::class)->name('show');
        });
    });

    // ─── Pelanggan ───────────────────────────────────────────────
    Route::prefix('pelanggan')->name('pelanggan.')->group(function () {
        Route::middleware('permission:pelanggan.buat')->group(function () {
            Route::get('/create', Pelanggan\Create::class)->name('create');
        });
        Route::middleware('permission:pelanggan.ubah')->group(function () {
            Route::get('/{pelanggan}/edit', Pelanggan\Edit::class)->name('edit');
        });
        Route::get('/{pelanggan}/ktp/preview', [PelangganMediaController::class, 'previewKtp'])->name('ktp.preview');
        Route::get('/{pelanggan}/dokumen/{media}/stream', [PelangganMediaController::class, 'streamDokumen'])->name('dokumen.stream');
        Route::middleware('permission:pelanggan.lihat')->group(function () {
            Route::get('/', Pelanggan\Index::class)->name('index');
            Route::get('/{pelanggan}', Pelanggan\Show::class)->name('show');
        });
    });

    // ─── Layanan Pelanggan ────────────────────────────────────────
    Route::prefix('layanan-pelanggan')->name('layanan-pelanggan.')->group(function () {
        Route::middleware('permission:layanan_pelanggan.buat')->group(function () {
            Route::get('/create/{pelanggan}', LayananPelanggan\Create::class)->name('create');
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
            Route::get('/create/{pelanggan?}', Invoice\Create::class)->name('create');
        });
        Route::get('/{invoice}/cetak', [InvoicePdfController::class, 'cetak'])->name('cetak');
        Route::middleware('permission:invoice.lihat')->group(function () {
            Route::get('/', Invoice\Index::class)->name('index');
            Route::get('/{invoice}', Invoice\Show::class)->name('show');
        });
    });

    // ─── Pembayaran ───────────────────────────────────────────────
    Route::prefix('pembayaran')->name('pembayaran.')->group(function () {
        Route::middleware('permission:pembayaran.lihat')->group(function () {
            Route::get('/', Pembayaran\Index::class)->name('index');
            Route::get('/transaksi-gateway', Pembayaran\TransaksiGateway\Index::class)->name('transaksi-gateway.index');
            Route::get('/transaksi-gateway/{transaksi}', Pembayaran\TransaksiGateway\Show::class)->name('transaksi-gateway.show');
        });
    });

    // ─── Pengaturan Gateway ────────────────────────────────────────
    Route::middleware('permission:payment_gateway.lihat')->group(function () {
        Route::get('/settings/gateway', PengaturanGateway::class)->name('settings.gateway');
        Route::get('/settings/template-deskripsi-tagihan', TemplateDeskripsiTagihan::class)->name('settings.template-deskripsi-tagihan');
    });

    // ─── Pengaturan Prefix Registrasi ──────────────────────────────
    Route::middleware('permission:prefix_registrasi.lihat')->group(function () {
        Route::get('/settings/prefix-registrasi', PengaturanPrefixRegistrasi::class)->name('settings.prefix-registrasi');
    });

    // ─── Media Library (termasuk Storage & S3 Monitoring, lihat ADR-0047) ──────

    Route::prefix('media-library')->name('media-library.')->group(function () {
        Route::middleware('permission:media_library.lihat')->group(function () {
            Route::get('/', MediaLibrary\Index::class)->name('index');
        });
    });

    // ─── SysBlast Gateway, Antrian & Template Pesan ────────────────
    Route::prefix('sysblas')->name('sysblas.')->group(function () {
        Route::middleware('permission:wa_gateway.lihat')->group(function () {
            Route::get('/koneksi', App\Livewire\Sysblas\Koneksi\Index::class)->name('koneksi.index');
            Route::get('/antrian', App\Livewire\Sysblas\Antrian\Index::class)->name('antrian.index');
        });
    });

    Route::middleware('permission:wa_gateway.lihat')->group(function () {
        Route::get('/settings/whatsapp', WhatsappSettings::class)->name('settings.whatsapp');
    });

    // ─── Aturan Pengingat Tagihan ─────────────────────────────────
    Route::prefix('billing/aturan-pengingat')->name('billing.aturan-pengingat.')->group(function () {
        Route::middleware('permission:invoice.lihat')->group(function () {
            Route::get('/', App\Livewire\Billing\AturanPengingat\Index::class)->name('index');
        });
    });

    // ─── Siklus Tagihan ───────────────────────────────────────────
    Route::middleware('permission:siklus_tagihan.ubah')->group(function () {
        Route::get('billing/siklus-tagihan', App\Livewire\Billing\SiklusTagihan\Index::class)->name('billing.siklus-tagihan.index');
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

    // ─── Barang (Inventaris) ────────────────────────────────────────
    Route::prefix('barang')->name('barang.')->group(function () {
        Route::middleware('permission:barang.buat')->group(function () {
            Route::get('/create', Barang\Create::class)->name('create');
        });
        Route::middleware('permission:barang.ubah')->group(function () {
            Route::get('/{barang}/edit', Barang\Edit::class)->name('edit');
        });
        Route::middleware('permission:barang.lihat')->group(function () {
            Route::get('/', Barang\Index::class)->name('index');
            Route::get('/{barang}/label', [BarangLabelController::class, 'cetak'])->name('label');
        });
    });

    Route::prefix('barang-masuk')->name('barang-masuk.')->group(function () {
        Route::middleware('permission:barang_masuk.catat')->group(function () {
            Route::get('/create', BarangMasuk\Create::class)->name('create');
        });
        Route::middleware('permission:barang_masuk.lihat')->group(function () {
            Route::get('/', BarangMasuk\Index::class)->name('index');
        });
    });

    Route::prefix('barang-keluar')->name('barang-keluar.')->group(function () {
        Route::middleware('permission:barang_keluar.catat')->group(function () {
            Route::get('/create', BarangKeluar\Create::class)->name('create');
        });
        Route::middleware('permission:barang_keluar.lihat')->group(function () {
            Route::get('/', BarangKeluar\Index::class)->name('index');
        });
    });

    // ─── Pengaturan Kode Barang (Jenis/Kondisi/Cabang) ─────────────
    Route::middleware('permission:pengaturan_barang.lihat')->group(function () {
        Route::get('/settings/jenis-barang', PengaturanJenisBarang::class)->name('settings.jenis-barang');
        Route::get('/settings/kondisi-barang', PengaturanKondisiBarang::class)->name('settings.kondisi-barang');
        Route::get('/settings/cabang-barang', PengaturanCabangBarang::class)->name('settings.cabang-barang');
    });

    // ─── Laporan Keuangan ─────────────────────────────────────────
    Route::prefix('laporan')->name('laporan.')->group(function () {
        Route::middleware('permission:laporan.lihat')->group(function () {
            Route::get('/billing', Laporan\Billing::class)->name('billing');
            Route::get('/data-pelanggan', Laporan\DataPelanggan::class)->name('data-pelanggan');
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

    // ─── IP Publik Dedicated ──────────────────────────────────────
    Route::prefix('ip-publik')->name('ip-publik.')->group(function () {
        Route::middleware('permission:ip_publik.buat')->group(function () {
            Route::get('/create', IpPublik\Create::class)->name('create');
        });
        Route::middleware('permission:ip_publik.ubah')->group(function () {
            Route::get('/{ipPublik}/edit', IpPublik\Edit::class)->name('edit');
        });
        Route::middleware('permission:ip_publik.lihat')->group(function () {
            Route::get('/', IpPublik\Index::class)->name('index');
        });
    });

    // ─── ODP (Optical Distribution Point) ─────────────────────────
    Route::prefix('odp')->name('odp.')->group(function () {
        Route::middleware('permission:odp.buat')->group(function () {
            Route::get('/create', Create::class)->name('create');
        });
        Route::middleware('permission:odp.ubah')->group(function () {
            Route::get('/{odp}/edit', Edit::class)->name('edit');
        });
        Route::middleware('permission:odp.lihat')->group(function () {
            Route::get('/', App\Livewire\Odp\Index::class)->name('index');
            Route::get('/{odp}', App\Livewire\Odp\Show::class)->name('show');
        });
    });

    // ─── Maps & Estimasi Kabel ────────────────────────────────────
    Route::prefix('maps')->name('maps.')->group(function () {
        Route::middleware('permission:pelanggan.lihat')->group(function () {
            Route::get('/lokasi', Lokasi::class)->name('lokasi');
            Route::get('/', fn () => redirect()->route('maps.lokasi'))->name('index');
            Route::get('/estimasi-kabel', EstimasiKabel::class)->name('estimasi-kabel');
        });
    });

    // ─── API Geospasial Maps Markers ──────────────────────────────
    Route::get('/api/maps/markers', MapMarkerController::class)->name('api.maps.markers');

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

// ─── Webhook Payment Gateways (Public & CSRF-Exempt) ─────────────
Route::middleware('throttle:webhook')->post('/webhook/payment/{gateway}', [PaymentWebhookController::class, 'handle'])->name('webhook.payment');

// ─── Webhook Xendit Legacy Aliases (Protected with xendit.token) ──
Route::middleware(['throttle:webhook', 'xendit.token'])->group(function () {
    Route::post('/webhook/xendit', [XenditWebhookController::class, 'handle'])->name('webhook.xendit');
    Route::post('/webhook/xendit/virtual-account', [XenditWebhookController::class, 'handle'])->name('webhook.xendit.va');
    Route::post('/webhook/xendit/qris', [XenditWebhookController::class, 'handle'])->name('webhook.xendit.qris');
});

// ─── Webhook WhatsApp / GOWA / WAHA (Public & CSRF-Exempt) ───────────────────────
Route::middleware('throttle:webhook')->post('/webhook/whatsapp', [WhatsappWebhookController::class, 'handle'])->name('webhook.whatsapp');

// ─── Portal Pelanggan (Guard: pelanggan) ─────────────────────────
// Definisi rute dipakai bersama oleh mount lama (path "/portal" di domain utama) dan mount
// baru (domain khusus Portal, lihat ADR-0049) supaya keduanya tidak pernah drift satu sama
// lain. Mount lama WAJIB tetap ada selamanya -- WhatsappService mengirim tautan bertanda
// tangan (signed URL, masa berlaku 30 hari) ke `portal.invoice.show` yang sudah terkirim ke
// pelanggan nyata; signature Laravel mencakup host + path, jadi memindahkan/menghapus mount
// ini akan langsung merusak tautan yang sudah beredar.
$registerRutePortalPelanggan = function () {
    // Auth Routes
    Route::get('/login', Login::class)->name('login');
    Route::get('/klaim-akun', KlaimAkun::class)->name('klaim-akun');

    // Halaman Tagihan Mandiri: dapat diakses TANPA login lewat tautan bertanda tangan
    // (signed URL) yang dikirim via notifikasi WhatsApp/email -- lihat Show::mount() untuk
    // validasi akses (sesi pelanggan ATAU signature valid untuk invoice ini) dan Show::bayar()
    // untuk redirect langsung ke Link Pembayaran Gateway (tanpa halaman estimasi biaya
    // terpisah -- lihat CONTEXT.md "Halaman Tagihan Mandiri").
    Route::get('/tagihan/{invoice}', Show::class)->name('invoice.show');
    // Rute lama dipertahankan sebagai redirect (bukan dihapus) untuk tautan /bayar yang
    // mungkin sudah ter-cache di notifikasi lama/riwayat browser.
    Route::get('/tagihan/{invoice}/bayar', fn (App\Models\Invoice $invoice) => redirect()->route('portal.invoice.show', $invoice))->name('invoice.bayar');

    Route::middleware('auth:pelanggan')->group(function () {
        Route::get('/', function () {
            return redirect()->route('portal.dashboard');
        })->name('index');

        Route::post('/logout', function () {
            Auth::guard('pelanggan')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('portal.login');
        })->name('logout');

        Route::get('/dashboard', Dashboard::class)->name('dashboard');
        Route::get('/tagihan', Index::class)->name('invoice.index');
        Route::get('/tagihan/{invoice}/cetak', [InvoicePdfController::class, 'cetak'])->name('invoice.cetak');
        Route::get('/profil', App\Livewire\Portal\Profil\Index::class)->name('profil');
        Route::get('/ganti-password', GantiPassword::class)->middleware('impersonate.protect')->name('ganti-password');
    });
};

// Mount lama, domain utama, path "/portal" -- permanen, lihat catatan di atas.
Route::prefix('portal')->name('portal.')->group($registerRutePortalPelanggan);

// Mount baru, domain khusus (mis. portal.gobilling.id), tanpa prefix path. Didaftarkan
// SETELAH mount lama supaya nama rute "portal.*" (dipakai WhatsappService, redirect
// impersonasi, dan seluruh navigasi internal Portal via route()) resolve ke domain ini --
// tautan baru yang dibuat sejak sekarang selalu mengarah ke domain khusus ini.
if ($portalDomain = config('app.portal_domain')) {
    Route::domain($portalDomain)->name('portal.')->group($registerRutePortalPelanggan);

    // Titik masuk handoff impersonasi staf->pelanggan lintas domain, lihat
    // ImpersonateController::take()/consumePortalHandoff() dan ADR-0049.
    Route::domain($portalDomain)
        ->middleware('throttle:10,1')
        ->get('/impersonate/consume', [ImpersonateController::class, 'consumePortalHandoff'])
        ->name('portal.impersonate.consume');
}

// ─── Impersonasi (Super Admin Only) ──────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::get('/impersonate/take/{id}/{guardName?}', [ImpersonateController::class, 'take'])->name('impersonate');
});
Route::get('/impersonate/leave', [ImpersonateController::class, 'leave'])->name('impersonate.leave');

require __DIR__.'/settings.php';
