<?php

namespace App\Livewire\PaketLayanan;

use App\Enums\MasaAktifSatuan;
use App\Enums\StatusPaket;
use App\Models\PaketLayanan;
use App\Models\ProfilBandwidth;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Paket Layanan')]
class Edit extends Component
{
    #[Locked]
    public int $paketId;

    public string $nama_paket = '';

    public ?int $profil_bandwidth_id = null;

    public ?float $harga = null;

    public ?int $masa_aktif_nilai = 1;

    public string $masa_aktif_satuan = 'bulan';

    public string $keterangan = '';

    public string $status = 'aktif';

    public function mount(PaketLayanan $paketLayanan): void
    {
        $this->authorize('update', $paketLayanan);

        $this->paketId = $paketLayanan->id;
        $this->nama_paket = $paketLayanan->nama_paket;
        $this->profil_bandwidth_id = $paketLayanan->profil_bandwidth_id;
        $this->harga = (float) $paketLayanan->harga;
        $this->masa_aktif_nilai = $paketLayanan->masa_aktif_nilai;
        $this->masa_aktif_satuan = $paketLayanan->masa_aktif_satuan->value;
        $this->keterangan = $paketLayanan->keterangan ?? '';
        $this->status = $paketLayanan->status->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_paket' => ['required', 'string', 'max:255', "unique:paket_layanan,nama_paket,{$this->paketId}"],
            'profil_bandwidth_id' => ['required', 'integer', 'exists:profil_bandwidth,id'],
            'harga' => ['required', 'numeric', 'min:1'],
            'masa_aktif_nilai' => ['required', 'integer', 'min:1'],
            'masa_aktif_satuan' => ['required', 'string', 'in:hari,bulan'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:aktif,nonaktif'],
        ];
    }

    public function save(): void
    {
        $paket = PaketLayanan::findOrFail($this->paketId);
        $this->authorize('update', $paket);
        $this->validate();

        $paket->update([
            'nama_paket' => $this->nama_paket,
            'profil_bandwidth_id' => $this->profil_bandwidth_id,
            'harga' => $this->harga,
            'masa_aktif_nilai' => $this->masa_aktif_nilai,
            'masa_aktif_satuan' => $this->masa_aktif_satuan,
            'keterangan' => $this->keterangan ?: null,
            'status' => $this->status,
        ]);

        Flux::toast(variant: 'success', text: "Paket {$paket->nama_paket} berhasil diperbarui.");
        $this->redirectRoute('paket-layanan.index', navigate: true);
    }

    public function render(): View
    {
        $profils = ProfilBandwidth::orderBy('max_limit_tx')->get();

        return view('livewire.paket-layanan.edit', [
            'profils' => $profils,
            'satuans' => MasaAktifSatuan::cases(),
            'statuses' => StatusPaket::cases(),
        ]);
    }
}
