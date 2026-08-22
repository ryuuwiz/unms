<?php

namespace App\Livewire\Settings;

use App\Models\Perusahaan as PerusahaanModel;
use Flux\Flux;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Profil Perusahaan')]
class Perusahaan extends Component
{
    use WithFileUploads;

    public string $nama_perusahaan = '';

    public string $nama_brand = 'GOBILLING';

    public ?string $tagline = null;

    public ?string $alamat = null;

    public ?string $kota = null;

    public ?string $kode_pos = null;

    public ?string $telepon = null;

    public ?string $whatsapp = null;

    public ?string $email = null;

    public ?string $website = null;

    public ?string $npwp = null;

    public ?string $nama_bank = null;

    public ?string $nomor_rekening = null;

    public ?string $atas_nama = null;

    public ?string $catatan_invoice = null;

    public ?string $syarat_ketentuan = null;

    public ?string $nama_penandatangan = null;

    public ?string $jabatan_penandatangan = null;

    /** @var mixed */
    public $logo = null;

    public ?string $existing_logo_url = null;

    public function mount(): void
    {
        $perusahaan = PerusahaanModel::default();

        $this->nama_perusahaan = $perusahaan->nama_perusahaan ?? 'GOBILLING';
        $this->nama_brand = $perusahaan->nama_brand ?? 'GOBILLING';
        $this->tagline = $perusahaan->tagline;
        $this->alamat = $perusahaan->alamat;
        $this->kota = $perusahaan->kota;
        $this->kode_pos = $perusahaan->kode_pos;
        $this->telepon = $perusahaan->telepon;
        $this->whatsapp = $perusahaan->whatsapp;
        $this->email = $perusahaan->email;
        $this->website = $perusahaan->website;
        $this->npwp = $perusahaan->npwp;
        $this->nama_bank = $perusahaan->nama_bank;
        $this->nomor_rekening = $perusahaan->nomor_rekening;
        $this->atas_nama = $perusahaan->atas_nama;
        $this->catatan_invoice = $perusahaan->catatan_invoice;
        $this->syarat_ketentuan = $perusahaan->syarat_ketentuan;
        $this->nama_penandatangan = $perusahaan->nama_penandatangan;
        $this->jabatan_penandatangan = $perusahaan->jabatan_penandatangan;
        $this->existing_logo_url = $perusahaan->logo_url;
    }

    public function hapusLogo(): void
    {
        $perusahaan = PerusahaanModel::default();

        if ($perusahaan->exists) {
            $perusahaan->clearMediaCollection('logo');
        }

        $this->logo = null;
        $this->existing_logo_url = null;

        Cache::forget(PerusahaanModel::CACHE_KEY);

        Flux::toast(variant: 'success', text: 'Logo perusahaan berhasil dihapus!');
    }

    public function save(): void
    {
        $this->validate([
            'nama_perusahaan' => ['required', 'string', 'max:255'],
            'nama_brand' => ['required', 'string', 'max:100'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'kota' => ['nullable', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'whatsapp' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'npwp' => ['nullable', 'string', 'max:50'],
            'nama_bank' => ['nullable', 'string', 'max:100'],
            'nomor_rekening' => ['nullable', 'string', 'max:100'],
            'atas_nama' => ['nullable', 'string', 'max:255'],
            'catatan_invoice' => ['nullable', 'string', 'max:2000'],
            'syarat_ketentuan' => ['nullable', 'string', 'max:2000'],
            'nama_penandatangan' => ['nullable', 'string', 'max:255'],
            'jabatan_penandatangan' => ['nullable', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        $perusahaan = PerusahaanModel::default();

        $data = [
            'nama_perusahaan' => trim($this->nama_perusahaan),
            'nama_brand' => trim($this->nama_brand),
            'tagline' => $this->tagline ? trim($this->tagline) : null,
            'alamat' => $this->alamat ? trim($this->alamat) : null,
            'kota' => $this->kota ? trim($this->kota) : null,
            'kode_pos' => $this->kode_pos ? trim($this->kode_pos) : null,
            'telepon' => $this->telepon ? trim($this->telepon) : null,
            'whatsapp' => $this->whatsapp ? trim($this->whatsapp) : null,
            'email' => $this->email ? trim($this->email) : null,
            'website' => $this->website ? trim($this->website) : null,
            'npwp' => $this->npwp ? trim($this->npwp) : null,
            'nama_bank' => $this->nama_bank ? trim($this->nama_bank) : null,
            'nomor_rekening' => $this->nomor_rekening ? trim($this->nomor_rekening) : null,
            'atas_nama' => $this->atas_nama ? trim($this->atas_nama) : null,
            'catatan_invoice' => $this->catatan_invoice ? trim($this->catatan_invoice) : null,
            'syarat_ketentuan' => $this->syarat_ketentuan ? trim($this->syarat_ketentuan) : null,
            'nama_penandatangan' => $this->nama_penandatangan ? trim($this->nama_penandatangan) : null,
            'jabatan_penandatangan' => $this->jabatan_penandatangan ? trim($this->jabatan_penandatangan) : null,
            'is_default' => true,
        ];

        if ($perusahaan->exists) {
            $perusahaan->update($data);
        } else {
            $perusahaan = PerusahaanModel::create($data);
        }

        if ($this->logo) {
            $perusahaan->addMedia($this->logo->getRealPath())
                ->usingFileName($this->logo->getClientOriginalName())
                ->toMediaCollection('logo');
        }

        Cache::forget(PerusahaanModel::CACHE_KEY);

        $this->existing_logo_url = $perusahaan->getFirstMediaUrl('logo') ?: null;
        $this->logo = null;

        Flux::toast(variant: 'success', text: 'Profil perusahaan dan template tagihan berhasil disimpan!');
    }

    public function render(): View
    {
        return view('livewire.settings.perusahaan');
    }
}
