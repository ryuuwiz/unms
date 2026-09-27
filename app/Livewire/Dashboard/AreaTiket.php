<?php

namespace App\Livewire\Dashboard;

use App\Enums\Ticket\StatusTicket;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Area Dashboard Ticketing & Support: Antrian Tiket Saya dan tren tiket masuk.
 */
#[Lazy]
class AreaTiket extends Component
{
    private const BARIS_ANTRIAN = 20;

    private const HARI_TREN = 30;

    public static function bolehLihat(User $user): bool
    {
        return $user->can('ticket.lihat');
    }

    /**
     * Jumlah tiket di Antrian Tiket Saya per status (hanya status terbuka).
     *
     * @return array<string, int>
     */
    public function getDistribusiProperty(): array
    {
        return Ticket::query()
            ->antrianUntuk(auth()->user())
            ->toBase()
            ->selectRaw('status, COUNT(*) as jumlah')
            ->groupBy('status')
            ->pluck('jumlah', 'status')
            ->map(fn ($jumlah) => (int) $jumlah)
            ->all();
    }

    /**
     * 20 tiket terbaru di Antrian Tiket Saya.
     *
     * @return Collection<int, Ticket>
     */
    public function getAntrianProperty(): Collection
    {
        return Ticket::query()
            ->antrianUntuk(auth()->user())
            ->with('pelanggan')
            ->latest('id')
            ->limit(self::BARIS_ANTRIAN)
            ->get();
    }

    /**
     * Tren Tiket Harian: tiket masuk per hari 30 hari terakhir, sebatas tiket yang boleh dilihat user.
     *
     * @return array{categories: array<int, string>, tiket: array<int, int>}
     */
    public function getTrenProperty(): array
    {
        $dari = today()->subDays(self::HARI_TREN - 1);

        $harian = Ticket::query()
            ->terlihatOleh(auth()->user())
            ->where('created_at', '>=', $dari)
            ->toBase()
            ->selectRaw('DATE(created_at) as tanggal, COUNT(*) as jumlah')
            ->groupByRaw('DATE(created_at)')
            ->pluck('jumlah', 'tanggal');

        $tren = ['categories' => [], 'tiket' => []];

        for ($hari = $dari->copy(); $hari->lte(today()); $hari = $hari->copy()->addDay()) {
            $tren['categories'][] = $hari->translatedFormat('d M');
            $tren['tiket'][] = (int) ($harian[$hari->toDateString()] ?? 0);
        }

        return $tren;
    }

    public function render(): View
    {
        $distribusi = $this->getDistribusiProperty();

        return view('livewire.dashboard.area-tiket', [
            'segmen' => collect(StatusTicket::cases())
                ->reject(fn (StatusTicket $status) => $status->isTerminal())
                ->map(fn (StatusTicket $status) => ['label' => $status->label(), 'nilai' => $distribusi[$status->value] ?? 0, 'warna' => $status->color()])
                ->values()
                ->all(),
            'totalAntrian' => array_sum($distribusi),
            'antrian' => $this->getAntrianProperty(),
            'tren' => $this->getTrenProperty(),
        ]);
    }
}
