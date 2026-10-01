<?php

namespace App\Livewire\Settings;

use App\Livewire\Concerns\KelolaIdentitasAplikasi;
use App\Models\PengaturanPrefixRegistrasi as PengaturanPrefixRegistrasiModel;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Spatie\MediaLibrary\HasMedia;

#[Layout('layouts.app')]
#[Title('Pengaturan Prefix Registrasi')]
class PengaturanPrefixRegistrasi extends Component
{
    use KelolaIdentitasAplikasi, WithFileUploads;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $kode = '';

    public string $nama = '';

    public bool $is_active = true;

    /** Logo Brand Pelanggan (ADR-0061), opsional. */
    public ?TemporaryUploadedFile $logo = null;

    public ?string $existing_logo_url = null;

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $prefix = PengaturanPrefixRegistrasiModel::findOrFail($id);

        $this->editingId = $prefix->id;
        $this->kode = $prefix->kode;
        $this->nama = $prefix->nama;
        $this->is_active = $prefix->is_active;
        $this->existing_logo_url = $prefix->logo_url;
        $this->isiIdentitasAplikasi($prefix);

        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->kode = strtoupper(trim($this->kode));

        $this->validate([
            'kode' => [
                'required', 'string', 'regex:/^[A-Z]{2,5}$/',
                Rule::unique('pengaturan_prefix_registrasi', 'kode')->ignore($this->editingId),
            ],
            'nama' => ['required', 'string', 'max:100'],
            'is_active' => ['required', 'boolean'],
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            ...$this->aturanIdentitasAplikasi(),
        ], [
            'kode.regex' => 'Kode prefix harus 2-5 huruf kapital (contoh: BF, ARS).',
            ...$this->pesanIdentitasAplikasi(),
        ]);

        $data = [
            'kode' => strtoupper($this->kode),
            'nama' => trim($this->nama),
            'is_active' => $this->is_active,
            ...$this->dataIdentitasAplikasi(),
        ];

        if ($this->editingId) {
            $prefix = PengaturanPrefixRegistrasiModel::findOrFail($this->editingId);
            $prefix->update($data);

            Flux::toast(variant: 'success', text: "Prefix '{$prefix->kode}' berhasil diperbarui.");
        } else {
            $prefix = PengaturanPrefixRegistrasiModel::create($data);

            Flux::toast(variant: 'success', text: "Prefix '{$prefix->kode}' berhasil ditambahkan.");
        }

        if ($this->logo) {
            $prefix->addMediaFromDisk(
                FileUploadConfiguration::path($this->logo->getFilename(), false),
                FileUploadConfiguration::disk()
            )
                ->usingFileName($this->logo->getClientOriginalName())
                ->toMediaCollection('logo');
        }

        $this->simpanIkonAplikasi($prefix);

        $this->showModal = false;
        $this->resetForm();
    }

    public function hapusLogo(): void
    {
        if ($this->editingId) {
            PengaturanPrefixRegistrasiModel::findOrFail($this->editingId)->clearMediaCollection('logo');
        }

        $this->logo = null;
        $this->existing_logo_url = null;
    }

    protected function modelIdentitasAplikasi(): ?HasMedia
    {
        return $this->editingId ? PengaturanPrefixRegistrasiModel::find($this->editingId) : null;
    }

    public function toggleStatus(int $id): void
    {
        $prefix = PengaturanPrefixRegistrasiModel::findOrFail($id);
        $prefix->update(['is_active' => ! $prefix->is_active]);

        $statusText = $prefix->is_active ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "Prefix '{$prefix->kode}' berhasil {$statusText}.");
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->kode = '';
        $this->nama = '';
        $this->is_active = true;
        $this->logo = null;
        $this->existing_logo_url = null;
        $this->isiIdentitasAplikasi(null);
    }

    public function render(): View
    {
        $query = PengaturanPrefixRegistrasiModel::query();

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('kode', 'like', "%{$this->search}%")
                    ->orWhere('nama', 'like', "%{$this->search}%");
            });
        }

        return view('livewire.settings.pengaturan-prefix-registrasi', [
            'prefixes' => $query->orderBy('kode')->get(),
        ]);
    }
}
