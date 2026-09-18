<?php

namespace App\Livewire\Billing\SiklusTagihan;

use App\Models\PengaturanSiklusTagihan;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Siklus Tagihan')]
class Index extends Component
{
    public ?int $hari_jatuh_tempo = 10;

    public ?int $hari_terbit_invoice = 24;

    public function mount(): void
    {
        $this->authorizeUbah();

        $pengaturan = PengaturanSiklusTagihan::ambil();
        $this->hari_jatuh_tempo = $pengaturan->hari_jatuh_tempo;
        $this->hari_terbit_invoice = $pengaturan->hari_terbit_invoice;
    }

    public function save(): void
    {
        $this->authorizeUbah();

        $validated = $this->validate([
            'hari_jatuh_tempo' => ['required', 'integer', 'between:1,28'],
            'hari_terbit_invoice' => ['required', 'integer', 'between:1,28', 'different:hari_jatuh_tempo'],
        ], attributes: [
            'hari_jatuh_tempo' => 'Hari Jatuh Tempo',
            'hari_terbit_invoice' => 'Hari Terbit Invoice',
        ]);

        PengaturanSiklusTagihan::ambil()->update($validated);

        Flux::toast(variant: 'success', text: 'Siklus Tagihan berhasil disimpan.');
    }

    public function render(): View
    {
        $valid = collect([$this->hari_jatuh_tempo, $this->hari_terbit_invoice])
            ->every(fn ($hari) => is_int($hari) && $hari >= 1 && $hari <= 28);

        return view('livewire.billing.siklus-tagihan.index', [
            'leadDays' => $valid ? (new PengaturanSiklusTagihan([
                'hari_jatuh_tempo' => $this->hari_jatuh_tempo,
                'hari_terbit_invoice' => $this->hari_terbit_invoice,
            ]))->leadDays() : null,
        ]);
    }

    private function authorizeUbah(): void
    {
        abort_unless(auth()->user()->can('siklus_tagihan.ubah'), 403);
    }
}
