<?php

namespace App\Livewire;

use Illuminate\View\View;
use Livewire\Component;

/**
 * Lonceng notifikasi database milik user login (Notifikasi NOC MikroTik, tiket, Horizon).
 */
class NotifikasiLonceng extends Component
{
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
