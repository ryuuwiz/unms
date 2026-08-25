<?php

namespace App\Livewire\Pelanggan;

use App\Models\Pelanggan;
use App\Services\CustomerDocumentService;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app')]
#[Title('Detail Pelanggan')]
class Show extends Component
{
    use WithFileUploads;

    public int $pelangganId;

    public string $activeTab = 'overview';

    /**
     * Modal states & properties
     */
    public bool $showKtpModal = false;

    public bool $showUploadKtpModal = false;

    public bool $showUploadDocModal = false;

    /** @var mixed */
    public $newKtpFile = null;

    /** @var mixed */
    public $docFile = null;

    public string $docJenis = 'MOU / Kontrak';

    public string $docNomor = '';

    public string $docKeterangan = '';

    /**
     * Cache status realtime PPP per ID layanan pelanggan.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $pppStatuses = [];

    public function mount(Pelanggan $pelanggan, MikrotikService $mikrotikService): void
    {
        $this->authorize('view', $pelanggan);
        $this->pelangganId = $pelanggan->id;
        $this->loadPppStatuses($pelanggan, $mikrotikService);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function openKtpModal(): void
    {
        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        $this->authorize('viewKtp', $pelanggan);

        $this->showKtpModal = true;
    }

    public function closeKtpModal(): void
    {
        $this->showKtpModal = false;
    }

    public function openUploadKtpModal(): void
    {
        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        $this->authorize('update', $pelanggan);

        $this->newKtpFile = null;
        $this->showUploadKtpModal = true;
    }

    public function closeUploadKtpModal(): void
    {
        $this->newKtpFile = null;
        $this->showUploadKtpModal = false;
    }

    public function saveKtp(CustomerDocumentService $documentService): void
    {
        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        $this->authorize('update', $pelanggan);

        $this->validate([
            'newKtpFile' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ], [
            'newKtpFile.required' => 'Silakan pilih foto KTP terlebih dahulu.',
            'newKtpFile.image' => 'Berkas harus berupa gambar (JPG, PNG, WEBP).',
            'newKtpFile.max' => 'Ukuran berkas KTP maksimal 5MB.',
        ]);

        $documentService->storeEncryptedMedia($pelanggan, $this->newKtpFile, 'ktp');

        $this->closeUploadKtpModal();

        Flux::toast(variant: 'success', text: 'Foto KTP berhasil dienkripsi dan disimpan.');
    }

    public function openUploadDocModal(): void
    {
        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        $this->authorize('uploadDokumen', $pelanggan);

        $this->docFile = null;
        $this->docJenis = 'MOU / Kontrak';
        $this->docNomor = '';
        $this->docKeterangan = '';
        $this->showUploadDocModal = true;
    }

    public function closeUploadDocModal(): void
    {
        $this->docFile = null;
        $this->showUploadDocModal = false;
    }

    public function saveDokumen(CustomerDocumentService $documentService): void
    {
        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        $this->authorize('uploadDokumen', $pelanggan);

        $this->validate([
            'docFile' => ['required', 'file', 'mimes:pdf,jpeg,png,jpg,webp', 'max:10240'],
            'docJenis' => ['required', 'string', 'max:100'],
            'docNomor' => ['nullable', 'string', 'max:100'],
            'docKeterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'docFile.required' => 'Silakan pilih berkas dokumen terlebih dahulu.',
            'docFile.mimes' => 'Format berkas dokumen harus PDF, JPG, PNG, atau WEBP.',
            'docFile.max' => 'Ukuran berkas maksimal 10MB.',
        ]);

        $documentService->storeEncryptedMedia(
            $pelanggan,
            $this->docFile,
            'dokumen',
            [
                'jenis_dokumen' => $this->docJenis,
                'nomor_dokumen' => $this->docNomor ?: null,
                'keterangan' => $this->docKeterangan ?: null,
                'uploaded_by' => Auth::user()?->name,
            ]
        );

        $this->closeUploadDocModal();

        Flux::toast(variant: 'success', text: "Dokumen {$this->docJenis} berhasil dienkripsi dan diunggah.");
    }

    public function deleteDokumen(int $mediaId): void
    {
        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        $this->authorize('deleteDokumen', $pelanggan);

        $media = $pelanggan->media()->where('id', $mediaId)->where('collection_name', 'dokumen')->firstOrFail();
        $fileName = $media->file_name;
        $media->delete();

        activity('pelanggan')
            ->performedOn($pelanggan)
            ->causedBy(Auth::user())
            ->withProperties(['file_name' => $fileName, 'media_id' => $mediaId])
            ->log("Menghapus dokumen {$fileName} milik pelanggan {$pelanggan->identitasLengkap()}");

        Flux::toast(variant: 'success', text: "Dokumen {$fileName} berhasil dihapus.");
    }

    public function refreshPppStatus(MikrotikService $mikrotikService): void
    {
        $pelanggan = Pelanggan::with(['layanans.router', 'layanans.paketLayanan.profilBandwidth'])
            ->findOrFail($this->pelangganId);

        $this->loadPppStatuses($pelanggan, $mikrotikService);

        Flux::toast(
            text: 'Status realtime PPP berhasil diperbarui dari MikroTik.',
            variant: 'success',
        );
    }

    public function loadPppStatuses(Pelanggan $pelanggan, MikrotikService $mikrotikService): void
    {
        $this->pppStatuses = [];

        $layanans = $pelanggan->relationLoaded('layanans') && $pelanggan->layanans->isNotEmpty()
            ? $pelanggan->layanans
            : $pelanggan->layanans()->with(['router', 'paketLayanan.profilBandwidth'])->get();

        foreach ($layanans as $layanan) {
            if ($layanan->router && ! empty($layanan->ppp_username)) {
                $this->pppStatuses[$layanan->id] = $mikrotikService->getPppStatus(
                    $layanan->router,
                    $layanan->ppp_username
                );
            }
        }
    }

    public function render(): View
    {
        $pelanggan = Pelanggan::with([
            'pembuat',
            'perumahan.kelurahan.kecamatan.kota',
            'layanans.paketLayanan.profilBandwidth',
            'layanans.router',
            'layanans.ipPool',
            'layanans.odpPort.odp',
            'media',
        ])->findOrFail($this->pelangganId);

        $activityLogs = Activity::forSubject($pelanggan)
            ->with('causer')
            ->latest()
            ->get();

        $dokumens = $pelanggan->getMedia('dokumen');
        $ktpMedia = $pelanggan->getKtpMedia();

        return view('livewire.pelanggan.show', [
            'pelanggan' => $pelanggan,
            'activityLogs' => $activityLogs,
            'pppStatuses' => $this->pppStatuses,
            'dokumens' => $dokumens,
            'ktpMedia' => $ktpMedia,
        ]);
    }
}
