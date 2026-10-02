<?php

namespace App\Livewire\Pembayaran\PelunasanSusulan;

use App\Enums\StatusPemindaian;
use App\Models\KasusPelunasanSusulan;
use App\Models\Pembayaran;
use App\Services\PaymentGateway\LaporanPelunasanSusulan;
use App\Services\PaymentGateway\PemindaianPelunasanSusulan;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Halaman Pelunasan Susulan (CONTEXT.md): pratinjau dan lunasi pembayaran PAID di Xendit yang belum
 * Lunas di sistem untuk rentang yang dipilih staf, di latar belakang -- serta daftar Kasus Pelunasan
 * Susulan yang masih terbuka.
 */
#[Layout('layouts.app')]
#[Title('Pelunasan Susulan')]
class Index extends Component
{
    use WithPagination;

    public string $dari = '';

    public ?int $kasusDitandai = null;

    public string $catatanPenanganan = '';

    public bool $tampilkanModalTandai = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Pembayaran::class);
        $this->dari = now(config('app.zona_waktu_bisnis'))->startOfYear()->toDateString();
    }

    public function pratinjau(PemindaianPelunasanSusulan $pemindaian): void
    {
        $this->authorize('viewAny', Pembayaran::class);
        $this->mulai($pemindaian, dryRun: true);
    }

    public function lunasiSekarang(PemindaianPelunasanSusulan $pemindaian): void
    {
        $this->authorize('kelolaPelunasanSusulan', Pembayaran::class);
        $this->mulai($pemindaian, dryRun: false);
    }

    /**
     * Batalkan pemindaian yang macet (worker antrean tidak mengambilnya) agar kuncinya lepas.
     */
    public function batalkanPemindaian(PemindaianPelunasanSusulan $pemindaian): void
    {
        $this->authorize('viewAny', Pembayaran::class);

        $id = $pemindaian->terakhir()['id'] ?? null;
        if ($id && $pemindaian->batalkan($id)) {
            Flux::toast(variant: 'success', text: 'Pemindaian dibatalkan.');

            return;
        }

        Flux::toast(variant: 'warning', text: 'Pemindaian ini masih berjalan normal dan tidak dapat dibatalkan.');
    }

    private function mulai(PemindaianPelunasanSusulan $pemindaian, bool $dryRun): void
    {
        $zonaWaktu = config('app.zona_waktu_bisnis');
        $this->validate(
            ['dari' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now($zonaWaktu)->toDateString()]],
            attributes: ['dari' => 'tanggal awal'],
        );

        $sejak = Carbon::parse($this->dari, $zonaWaktu)->startOfDay();

        if ($pemindaian->mulai($sejak, $dryRun, auth()->user()) === null) {
            Flux::toast(variant: 'warning', text: 'Pemindaian Pelunasan Susulan lain sedang berjalan (termasuk jadwal harian 02:15). Coba lagi setelah selesai.');

            return;
        }

        Flux::toast(variant: 'success', text: $dryRun ? 'Pratinjau dimulai di latar belakang.' : 'Pelunasan dimulai di latar belakang.');
    }

    public function bukaTandaiDitangani(int $id): void
    {
        $this->authorize('kelolaPelunasanSusulan', Pembayaran::class);
        $this->kasusDitandai = KasusPelunasanSusulan::terbuka()->findOrFail($id)->id;
        $this->catatanPenanganan = '';
        $this->tampilkanModalTandai = true;
        $this->resetValidation();
    }

    public function tandaiDitangani(LaporanPelunasanSusulan $laporan): void
    {
        $this->authorize('kelolaPelunasanSusulan', Pembayaran::class);
        $this->validate(['catatanPenanganan' => ['nullable', 'string', 'max:1000']], attributes: ['catatanPenanganan' => 'catatan']);

        $laporan->tandaiDitangani(KasusPelunasanSusulan::terbuka()->findOrFail($this->kasusDitandai), auth()->user(), trim($this->catatanPenanganan));

        $this->reset('kasusDitandai', 'catatanPenanganan', 'tampilkanModalTandai');
        Flux::toast(variant: 'success', text: 'Kasus ditandai Sudah Ditangani.');
    }

    public function render(PemindaianPelunasanSusulan $pemindaian): View
    {
        $terakhir = $pemindaian->terakhir();

        return view('livewire.pembayaran.pelunasan-susulan.index', [
            'pemindaian' => $terakhir,
            'sedangBerjalan' => ($terakhir['status'] ?? null) === StatusPemindaian::Berjalan,
            'macet' => $terakhir !== null && $pemindaian->macet($terakhir),
            'kolom' => PemindaianPelunasanSusulan::KOLOM,
            'kasusTerbuka' => KasusPelunasanSusulan::terbuka()->with('invoice.pelanggan')->latest('id')->paginate(20),
        ]);
    }
}
