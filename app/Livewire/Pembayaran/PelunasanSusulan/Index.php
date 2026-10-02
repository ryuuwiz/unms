<?php

namespace App\Livewire\Pembayaran\PelunasanSusulan;

use App\Models\Pembayaran;
use App\Services\PaymentGateway\PemindaianPelunasanSusulan;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Halaman Pelunasan Susulan (CONTEXT.md): pratinjau dan lunasi pembayaran PAID di Xendit yang belum
 * Lunas di sistem untuk rentang yang dipilih staf, di latar belakang.
 */
#[Layout('layouts.app')]
#[Title('Pelunasan Susulan')]
class Index extends Component
{
    public string $dari = '';

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
        $this->authorize('viewAny', Pembayaran::class);
        abort_unless(auth()->user()->can('payment_gateway.ubah'), 403);
        $this->mulai($pemindaian, dryRun: false);
    }

    private function mulai(PemindaianPelunasanSusulan $pemindaian, bool $dryRun): void
    {
        $this->validate(['dari' => ['required', 'date_format:Y-m-d', 'before_or_equal:today']], attributes: ['dari' => 'tanggal awal']);

        $sejak = Carbon::parse($this->dari, config('app.zona_waktu_bisnis'))->startOfDay();

        if ($pemindaian->mulai($sejak, $dryRun, auth()->user()) === null) {
            Flux::toast(variant: 'warning', text: 'Pemindaian Pelunasan Susulan lain sedang berjalan (termasuk jadwal harian 02:15). Coba lagi setelah selesai.');

            return;
        }

        Flux::toast(variant: 'success', text: $dryRun ? 'Pratinjau dimulai di latar belakang.' : 'Pelunasan dimulai di latar belakang.');
    }

    public function render(PemindaianPelunasanSusulan $pemindaian): View
    {
        return view('livewire.pembayaran.pelunasan-susulan.index', [
            'pemindaian' => $pemindaian->terakhir(),
        ]);
    }
}
