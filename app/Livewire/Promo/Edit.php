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
#[Title('Edit Promo')]
class Edit extends Component
{
    public Promo $promo;

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

    public function mount(Promo $promo): void
    {
        $this->authorize('update', $promo);

        $this->promo = $promo;
        $this->kode_promo = $promo->kode_promo;
        $this->nama_promo = $promo->nama_promo;
        $this->jenis = $promo->jenis->value;
        $this->deskripsi = $promo->deskripsi ?? '';
        $this->diskon_tipe = $promo->diskon_tipe?->value ?? 'nominal';
        $this->diskon_nilai = $promo->diskon_nilai ? (float) $promo->diskon_nilai : null;
        $this->bonus_bulan = $promo->bonus_bulan;
        $this->minimal_nominal_invoice = $promo->minimal_nominal_invoice ? (float) $promo->minimal_nominal_invoice : null;
        $this->kuota_global = $promo->kuota_global;
        $this->berlaku_dari = $promo->berlaku_dari?->toDateString();
        $this->berlaku_sampai = $promo->berlaku_sampai?->toDateString();
        $this->aktif = $promo->aktif;
    }

    public function save(): void
    {
        $this->authorize('update', $this->promo);

        $this->validate([
            'kode_promo' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9_-]+$/', "unique:promo,kode_promo,{$this->promo->id}"],
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

        $this->promo->update([
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
        ]);

        Flux::toast(variant: 'success', text: "Promo {$this->promo->kode_promo} berhasil diperbarui.");

        $this->redirectRoute('promo.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.promo.edit', [
            'jenises' => JenisPromo::cases(),
            'diskonTipes' => DiskonTipe::cases(),
        ]);
    }
}
