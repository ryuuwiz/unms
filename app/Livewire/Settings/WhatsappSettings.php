<?php

namespace App\Livewire\Settings;

use App\Enums\Wa\KategoriTemplateWa;
use App\Models\User;
use App\Models\WaTemplate;
use App\Services\Wablas\WablasClient;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan WhatsApp Gateway & Template')]
class WhatsappSettings extends Component
{
    // State Device Info
    /** @var array<string, mixed> */
    public array $deviceInfo = [];

    public bool $isCheckingDevice = false;

    // State Test Message
    public string $testPhone = '';

    public string $testMessage = 'Halo! Ini adalah pesan uji coba integrasi WhatsApp Gateway WABLAS dari GOBILLING.';

    public bool $isSendingTest = false;

    // State Modal Template CRUD
    public bool $showTemplateModal = false;

    public ?int $editingTemplateId = null;

    public string $template_kode = '';

    public string $template_nama = '';

    public string $template_kategori = 'tagihan';

    public string $template_konten = '';

    public string $template_keterangan = '';

    public bool $template_is_aktif = true;

    // State Filter Kategori Template
    public string $filterKategori = '';

    public function mount(WablasClient $client): void
    {
        $this->authorize('viewAny', User::class);
        $this->refreshDeviceInfo($client);
    }

    public function refreshDeviceInfo(WablasClient $client): void
    {
        $this->isCheckingDevice = true;
        try {
            $this->deviceInfo = $client->getDeviceInfo();
        } catch (\Throwable $e) {
            $this->deviceInfo = [
                'connected' => false,
                'phone' => config('services.wablas.number', '-'),
                'quota' => '-',
                'expired_at' => null,
                'message' => 'Gagal terhubung: '.$e->getMessage(),
                'raw' => [],
            ];
        } finally {
            $this->isCheckingDevice = false;
        }
    }

    public function kirimPesanUjiCoba(WablasClient $client): void
    {
        $this->validate([
            'testPhone' => ['required', 'string', 'min:9', 'max:20'],
            'testMessage' => ['required', 'string', 'min:3', 'max:1000'],
        ], [
            'testPhone.required' => 'Nomor HP tujuan wajib diisi.',
            'testMessage.required' => 'Pesan uji coba wajib diisi.',
        ]);

        $this->isSendingTest = true;

        try {
            $result = $client->sendMessage($this->testPhone, $this->testMessage);

            if ($result['success']) {
                Flux::toast(variant: 'success', text: "Pesan uji coba berhasil dikirim ke {$this->testPhone}!");
            } else {
                Flux::toast(variant: 'danger', text: "Gagal kirim: {$result['message']}");
            }
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: 'Terjadi kesalahan: '.$e->getMessage());
        } finally {
            $this->isSendingTest = false;
        }
    }

    public function openCreateTemplateModal(): void
    {
        $this->resetTemplateForm();
        $this->showTemplateModal = true;
    }

    public function openEditTemplateModal(int $id): void
    {
        $template = WaTemplate::findOrFail($id);

        $this->editingTemplateId = $template->id;
        $this->template_kode = $template->kode;
        $this->template_nama = $template->nama;
        $this->template_kategori = $template->kategori->value;
        $this->template_konten = $template->konten;
        $this->template_keterangan = $template->keterangan ?? '';
        $this->template_is_aktif = (bool) $template->is_aktif;

        $this->showTemplateModal = true;
    }

    public function simpanTemplate(): void
    {
        $this->validate([
            'template_kode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('wa_template', 'kode')->ignore($this->editingTemplateId),
            ],
            'template_nama' => ['required', 'string', 'max:255'],
            'template_kategori' => ['required', Rule::enum(KategoriTemplateWa::class)],
            'template_konten' => ['required', 'string', 'min:5', 'max:3000'],
            'template_keterangan' => ['nullable', 'string', 'max:500'],
            'template_is_aktif' => ['required', 'boolean'],
        ], [
            'template_kode.required' => 'Kode template wajib diisi (misal: pengingat_tagihan_h3).',
            'template_nama.required' => 'Nama template wajib diisi.',
            'template_konten.required' => 'Isi pesan template wajib diisi.',
        ]);

        $data = [
            'kode' => trim($this->template_kode),
            'nama' => trim($this->template_nama),
            'kategori' => $this->template_kategori,
            'konten' => trim($this->template_konten),
            'keterangan' => $this->template_keterangan ? trim($this->template_keterangan) : null,
            'is_aktif' => $this->template_is_aktif,
        ];

        if ($this->editingTemplateId) {
            $template = WaTemplate::findOrFail($this->editingTemplateId);
            $template->update($data);
            Flux::toast(variant: 'success', text: "Template '{$template->nama}' berhasil diperbarui.");
        } else {
            $template = WaTemplate::create($data);
            Flux::toast(variant: 'success', text: "Template '{$template->nama}' berhasil dibuat.");
        }

        $this->showTemplateModal = false;
        $this->resetTemplateForm();
    }

    public function toggleTemplateStatus(int $id): void
    {
        $template = WaTemplate::findOrFail($id);
        $template->update(['is_aktif' => ! $template->is_aktif]);

        $statusText = $template->is_aktif ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "Template '{$template->nama}' berhasil {$statusText}.");
    }

    public function hapusTemplate(int $id): void
    {
        $template = WaTemplate::findOrFail($id);

        if ($template->aturanPengingat()->exists()) {
            Flux::toast(variant: 'danger', text: "Template '{$template->nama}' tidak dapat dihapus karena sedang digunakan oleh aturan pengingat tagihan aktif.");

            return;
        }

        $nama = $template->nama;
        $template->delete();

        Flux::toast(variant: 'success', text: "Template '{$nama}' berhasil dihapus.");
    }

    protected function resetTemplateForm(): void
    {
        $this->editingTemplateId = null;
        $this->template_kode = '';
        $this->template_nama = '';
        $this->template_kategori = 'tagihan';
        $this->template_konten = '';
        $this->template_keterangan = '';
        $this->template_is_aktif = true;
    }

    public function render(): View
    {
        $query = WaTemplate::query();

        if ($this->filterKategori) {
            $query->where('kategori', $this->filterKategori);
        }

        $templates = $query->orderBy('kategori')->orderBy('nama')->get();

        return view('livewire.settings.whatsapp-settings', [
            'templates' => $templates,
            'kategoriList' => KategoriTemplateWa::cases(),
        ]);
    }
}
