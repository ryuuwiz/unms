<?php

namespace App\Livewire\Promo;

use App\Enums\DiskonTipe;
use App\Enums\JenisPromo;
use App\Models\Promo;
use Flux\Flux;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Promo')]
class Create extends Component
{
    public string $kode_promo = '';

    public string $nama_promo = '';

    public string $jenis = 'diskon';

    public string $deskripsi = '';

    public string $diskon_tipe = 'nominal';

    public ?float $diskon_nilai = null;

    public ?int $bonus_bulan = null;

    public ?float $minimal_nominal_invoice = null;

    public ?int $kuota_global = null;

    public ?string $berlaku_dari = null;

    public ?string $berlaku_sampai = null;

    public bool $aktif = true;

    public function mount(): void
    {
        $this->authorize('create', Promo::class);
    }

    public function save(): void
    {
        $this->authorize('create', Promo::class);

        $this->validate([
            'kode_promo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', 'unique:promo,kode_promo'],
            'nama_promo' => ['required', 'string', 'max:100'],
            'jenis' => ['required', 'string', 'in:diskon,bonus_durasi'],
            'diskon_tipe' => ['nullable', 'string', 'in:persentase,nominal'],
            'diskon_nilai' => ['nullable', 'numeric', 'min:0'],
            'bonus_bulan' => ['nullable', 'integer', 'min:1'],
            'minimal_nominal_invoice' => ['nullable', 'numeric', 'min:0'],
            'kuota_global' => ['nullable', 'integer', 'min:1'],
            'berlaku_dari' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_dari'],
        ], [
            'kode_promo.required' => 'Kode promo wajib diisi.',
            'kode_promo.unique' => 'Kode promo sudah digunakan.',
            'kode_promo.regex' => 'Kode promo hanya boleh berisi huruf kapital, angka, garis bawah, dan strip.',
            'nama_promo.required' => 'Nama promo wajib diisi.',
        ]);

        $promo = Promo::create([
            'kode_promo' => Str::upper($this->kode_promo),
            'nama_promo' => $this->nama_promo,
            'jenis' => $this->jenis,
            'deskripsi' => $this->deskripsi ?: null,
            'diskon_tipe' => $this->jenis === 'diskon' ? $this->diskon_tipe : null,
            'diskon_nilai' => $this->jenis === 'diskon' ? $this->diskon_nilai : null,
            'bonus_bulan' => $this->jenis === 'bonus_durasi' ? $this->bonus_bulan : null,
            'minimal_nominal_invoice' => $this->minimal_nominal_invoice ?: null,
            'kuota_global' => $this->kuota_global ?: null,
            'berlaku_dari' => $this->berlaku_dari ?: null,
            'berlaku_sampai' => $this->berlaku_sampai ?: null,
            'aktif' => $this->aktif,
            'tampil_ke_customer' => true,
        ]);

        Flux::toast(variant: 'success', text: "Promo {$promo->kode_promo} berhasil ditambahkan.");

        $this->redirectRoute('promo.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.promo.create', [
            'jenises' => JenisPromo::cases(),
            'diskonTipes' => DiskonTipe::cases(),
        ]);
    }
}
