<?php

namespace App\Livewire;

use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Lonceng notifikasi (tombol mengambang kanan bawah) milik user login (Notifikasi NOC MikroTik, tiket, Horizon).
 * Notifikasi baru juga diteruskan ke notifikasi browser (Notification API) bila user mengizinkan.
 */
class NotifikasiLonceng extends Component
{
    /** Batas bawah jendela [batas, detik sekarang) notifikasi yang belum diteruskan ke browser. */
    #[Locked]
    public string $batasBrowser = '';

    public function mount(): void
    {
        // Notifikasi lama tidak dimunculkan ulang setiap halaman dibuka.
        $this->batasBrowser = Carbon::now()->startOfSecond()->toDateTimeString();
    }

    /**
     * Dipanggil poll: kirim notifikasi yang dibuat sejak poll sebelumnya ke browser. Jendela berbatas
     * awal detik, sehingga notifikasi di detik yang sama dengan poll terambil di poll berikutnya.
     */
    public function periksaBaru(): void
    {
        $sampai = Carbon::now()->startOfSecond()->toDateTimeString();

        $baru = auth('web')->user()?->unreadNotifications()
            ->where('created_at', '>=', $this->batasBrowser)
            ->where('created_at', '<', $sampai)
            ->oldest()
            ->limit(5)
            ->get() ?? collect();

        $this->batasBrowser = $sampai;

        if ($baru->isNotEmpty()) {
            $this->dispatch('notifikasi-browser', notifikasi: $baru->map(fn ($n) => [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Notifikasi',
                'body' => $n->data['message'] ?? '',
                'url' => $n->data['url'] ?? null,
            ])->values()->all());
        }
    }

    public function tandaiDibaca(string $id): void
    {
        auth('web')->user()?->unreadNotifications()->whereKey($id)->update(['read_at' => now()]);
    }

    public function tandaiSemuaDibaca(): void
    {
        auth('web')->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        $user = auth('web')->user();

        return view('livewire.notifikasi-lonceng', [
            'notifikasi' => $user?->notifications()->latest()->limit(10)->get() ?? collect(),
            'belumDibaca' => $user?->unreadNotifications()->count() ?? 0,
        ]);
    }
}
