<?php

namespace App\Livewire\PaketLayanan;

use App\Enums\MasaAktifSatuan;
use App\Enums\StatusPaket;
use App\Models\PaketLayanan;
use App\Models\ProfilBandwidth;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Paket Layanan')]
class Create extends Component
{
    public string $nama_paket = '';

    public ?int $profil_bandwidth_id = null;

    public ?float $harga = null;

    public ?int $masa_aktif_nilai = 1;

    public string $masa_aktif_satuan = 'bulan';

    public string $keterangan = '';

    public string $status = 'aktif';

    public function mount(): void
    {
        $this->authorize('create', PaketLayanan::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'nama_paket' => ['required', 'string', 'max:255', 'unique:paket_layanan,nama_paket'],
            'profil_bandwidth_id' => ['required', 'integer', 'exists:profil_bandwidth,id'],
            'harga' => ['required', 'numeric', 'min:1'],
            'masa_aktif_nilai' => ['required', 'integer', 'min:1'],
            'masa_aktif_satuan' => ['required', 'string', 'in:hari,bulan'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', 'string', 'in:aktif,nonaktif'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'nama_paket.required' => 'Nama paket wajib diisi.',
            'nama_paket.unique' => 'Nama paket sudah terdaftar.',
            'profil_bandwidth_id.required' => 'Profil bandwidth wajib dipilih.',
            'harga.required' => 'Harga paket wajib diisi.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', PaketLayanan::class);
        $this->validate();

        $paket = PaketLayanan::create([
            'nama_paket' => $this->nama_paket,
            'profil_bandwidth_id' => $this->profil_bandwidth_id,
            'harga' => $this->harga,
            'masa_aktif_nilai' => $this->masa_aktif_nilai,
            'masa_aktif_satuan' => $this->masa_aktif_satuan,
            'keterangan' => $this->keterangan ?: null,
            'status' => $this->status,
        ]);

        Flux::toast(variant: 'success', text: "Paket {$paket->nama_paket} berhasil ditambahkan.");
        $this->redirectRoute('paket-layanan.index', navigate: true);
    }

    public function render(): View
    {
        $profils = ProfilBandwidth::orderBy('max_limit_tx')->get();

        return view('livewire.paket-layanan.create', [
            'profils' => $profils,
            'satuans' => MasaAktifSatuan::cases(),
            'statuses' => StatusPaket::cases(),
        ]);
    }
}
