<?php

namespace App\Livewire\Settings\MetodePembayaran;

use App\Models\ChannelPembayaran;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Metode Pembayaran')]
class Index extends Component
{
    public function toggleStatus(int $id): void
    {
        $this->authorize('payment_gateway.ubah');

        $channel = ChannelPembayaran::findOrFail($id);
        $channel->update(['is_active' => ! $channel->is_active]);

        Flux::toast(variant: 'success', text: "Metode '{$channel->kode}' ".($channel->is_active ? 'diaktifkan (ON).' : 'dinonaktifkan (OFF).'));
    }

    public function hapus(int $id): void
    {
        $this->authorize('payment_gateway.hapus');

        $channel = ChannelPembayaran::findOrFail($id);
        $channel->delete();

        Flux::toast(variant: 'success', text: "Metode '{$channel->kode}' berhasil dihapus.");
    }

    public function render(): View
    {
        return view('livewire.settings.metode-pembayaran.index', [
            'channels' => ChannelPembayaran::with('pengaturanGateway')
                ->orderBy('pengaturan_gateway_id')
                ->orderBy('tipe')
                ->orderBy('kode')
                ->get(),
        ]);
    }
}
