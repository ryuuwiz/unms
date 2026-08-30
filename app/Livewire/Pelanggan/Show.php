<?php

namespace App\Livewire\Pelanggan;

use App\Enums\StatusInvoice;
use App\Models\AkunPelanggan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\CustomerDocumentService;
use App\Services\Mikrotik\MikrotikService;
use Exception;
use Flux\Flux;
use Illuminate\Support\Carbon;
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

    public bool $showNik = false;

    /**
     * Modal states & properties
     */
    public bool $showKtpModal = false;

    public bool $showUploadKtpModal = false;

    public bool $showUploadDocModal = false;

    public bool $showBayarModal = false;

    public bool $showUbahPaketModal = false;

    public ?int $selectedLayananId = null;

    public ?int $newPaketId = null;

    public ?int $selectedInvoiceId = null;

    public string $bayarMetode = 'manual_admin';

    public ?float $bayarJumlah = 0.0;

    public string $bayarReferensi = '';

    public string $bayarTanggal = '';

    public string $bayarCatatan = '';

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

    public function toggleShowNik(): void
    {
        $this->showNik = ! $this->showNik;
    }

    public function resetPasswordPortal(): void
    {
        $pelanggan = Pelanggan::with('akunPelanggan')->findOrFail($this->pelangganId);
        $this->authorize('update', $pelanggan);

        if ($pelanggan->akunPelanggan) {
            $pelanggan->akunPelanggan->update([
                'password' => '12345678',
            ]);
        } else {
            if (empty($pelanggan->email)) {
                Flux::toast(variant: 'danger', text: 'Pelanggan belum memiliki alamat email untuk akun portal.');

                return;
            }

            AkunPelanggan::create([
                'pelanggan_id' => $pelanggan->id,
                'email' => strtolower(trim($pelanggan->email)),
                'password' => '12345678',
            ]);
        }

        activity('pelanggan')
            ->performedOn($pelanggan)
            ->causedBy(Auth::user())
            ->log("Mereset password akun portal pelanggan {$pelanggan->identitasLengkap()} ke default");

        Flux::toast(variant: 'success', text: 'Password portal pelanggan berhasil direset ke default (12345678).');
    }

    public function openBayarModal(int $invoiceId): void
    {
        $invoice = Invoice::where('pelanggan_id', $this->pelangganId)->findOrFail($invoiceId);
        $this->selectedInvoiceId = $invoice->id;
        $this->bayarMetode = 'manual_admin';
        $this->bayarJumlah = (float) $invoice->jumlah_setelah_promo;
        $this->bayarTanggal = Carbon::now()->format('Y-m-d\TH:i');
        $this->bayarReferensi = '';
        $this->bayarCatatan = '';
        $this->showBayarModal = true;
    }

    public function closeBayarModal(): void
    {
        $this->showBayarModal = false;
        $this->selectedInvoiceId = null;
    }

    public function prosesBayarInvoice(BillingService $billingService, MikrotikService $mikrotikService): void
    {
        $this->authorize('create', Pembayaran::class);

        $this->validate([
            'bayarMetode' => ['required', 'string', 'in:manual_admin,transfer'],
            'bayarJumlah' => ['required', 'numeric', 'min:1'],
            'bayarTanggal' => ['required', 'date'],
            'bayarReferensi' => ['nullable', 'string', 'max:100'],
            'bayarCatatan' => ['nullable', 'string', 'max:500'],
        ], [
            'bayarMetode.required' => 'Pilih metode pembayaran.',
            'bayarJumlah.required' => 'Jumlah pembayaran wajib diisi.',
            'bayarTanggal.required' => 'Tanggal pembayaran wajib diisi.',
        ]);

        $invoice = Invoice::where('pelanggan_id', $this->pelangganId)->findOrFail($this->selectedInvoiceId);

        try {
            /** @var User $actor */
            $actor = Auth::user();

            $billingService->prosesPembayaranManual(
                invoice: $invoice,
                payload: [
                    'metode' => $this->bayarMetode,
                    'jumlah_dibayar' => $this->bayarJumlah,
                    'referensi_transaksi' => $this->bayarReferensi ?: null,
                    'dibayar_pada' => $this->bayarTanggal,
                    'catatan' => $this->bayarCatatan ?: null,
                ],
                actor: $actor
            );

            $this->closeBayarModal();

            // Refresh ppp status
            $pelanggan = Pelanggan::with(['layanans.router', 'layanans.paketLayanan.profilBandwidth'])->findOrFail($this->pelangganId);
            $this->loadPppStatuses($pelanggan, $mikrotikService);

            Flux::toast(variant: 'success', text: "Pembayaran invoice {$invoice->no_invoice} berhasil dicatat & layanan diperpanjang!");
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
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

    public function openUbahPaketModal(int $layananId): void
    {
        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        $layanan = $pelanggan->layanans()->findOrFail($layananId);
        $this->authorize('update', $layanan);

        $this->selectedLayananId = $layanan->id;
        $this->newPaketId = $layanan->paket_layanan_id;
        $this->showUbahPaketModal = true;
    }

    public function closeUbahPaketModal(): void
    {
        $this->showUbahPaketModal = false;
        $this->selectedLayananId = null;
        $this->newPaketId = null;
    }

    public function prosesUbahPaket(MikrotikService $mikrotikService): void
    {
        $this->validate([
            'newPaketId' => ['required', 'integer', 'exists:paket_layanan,id'],
        ], [
            'newPaketId.required' => 'Pilih paket layanan baru.',
            'newPaketId.exists' => 'Paket layanan tidak valid.',
        ]);

        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        /** @var LayananPelanggan $layanan */
        $layanan = $pelanggan->layanans()->with(['paketLayanan', 'router'])->findOrFail($this->selectedLayananId);
        $this->authorize('update', $layanan);

        if ($layanan->paket_layanan_id === (int) $this->newPaketId) {
            $this->closeUbahPaketModal();
            Flux::toast(variant: 'warning', text: 'Paket yang dipilih sama dengan paket yang sedang aktif.');

            return;
        }

        $oldPaketNama = $layanan->paketLayanan?->nama_paket ?? 'Lama';
        $newPaket = PaketLayanan::with('profilBandwidth')->findOrFail($this->newPaketId);

        // Update paket_layanan_id (memicu LayananPelangganObserver -> UpdatePppoeProfileJob)
        $layanan->update([
            'paket_layanan_id' => $newPaket->id,
        ]);

        activity('layanan_pelanggan')
            ->performedOn($layanan)
            ->causedBy(Auth::user())
            ->withProperties([
                'old_paket_id' => $layanan->getOriginal('paket_layanan_id'),
                'old_paket_nama' => $oldPaketNama,
                'new_paket_id' => $newPaket->id,
                'new_paket_nama' => $newPaket->nama_paket,
            ])
            ->log("Mengubah paket {$layanan->site_id} ({$layanan->ppp_username}) dari {$oldPaketNama} ke {$newPaket->nama_paket}");

        $this->closeUbahPaketModal();

        // Refresh ppp status
        $this->loadPppStatuses($pelanggan, $mikrotikService);

        Flux::toast(
            variant: 'success',
            text: "Paket berhasil diubah ke {$newPaket->nama_paket}. Job sinkronisasi profil ke MikroTik telah dikirim ke antrean."
        );
    }

    public function render(): View
    {
        $pelanggan = Pelanggan::with([
            'pembuat',
            'akunPelanggan',
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

        $invoicesAktif = Invoice::where('pelanggan_id', $this->pelangganId)
            ->whereIn('status', [StatusInvoice::MenungguPembayaran, StatusInvoice::Kadaluarsa])
            ->with(['layananPelanggan.paketLayanan', 'layananPelanggan.router', 'promo', 'transaksiPaymentGateways' => fn ($q) => $q->latest('id')])
            ->orderByDesc('tanggal_terbit')
            ->get();

        $invoicesLunas = Invoice::where('pelanggan_id', $this->pelangganId)
            ->where('status', StatusInvoice::Lunas)
            ->with(['layananPelanggan.paketLayanan', 'promo', 'pembayarans.dicatatOleh'])
            ->orderByDesc('tanggal_lunas')
            ->orderByDesc('id')
            ->get();

        $invoicesDihapus = Invoice::onlyTrashed()
            ->where('pelanggan_id', $this->pelangganId)
            ->with(['layananPelanggan.paketLayanan', 'dihapusOleh'])
            ->orderByDesc('deleted_at')
            ->get();

        $selectedInvoice = $this->selectedInvoiceId
            ? Invoice::with(['layananPelanggan.paketLayanan', 'promo'])->find($this->selectedInvoiceId)
            : null;

        $pakets = PaketLayanan::aktif()->with('profilBandwidth')->orderBy('nama_paket')->get();

        $selectedLayananForModal = $this->selectedLayananId
            ? $pelanggan->layanans->firstWhere('id', $this->selectedLayananId)
            : null;

        return view('livewire.pelanggan.show', [
            'pelanggan' => $pelanggan,
            'activityLogs' => $activityLogs,
            'pppStatuses' => $this->pppStatuses,
            'dokumens' => $dokumens,
            'ktpMedia' => $ktpMedia,
            'invoicesAktif' => $invoicesAktif,
            'invoicesLunas' => $invoicesLunas,
            'invoicesDihapus' => $invoicesDihapus,
            'selectedInvoice' => $selectedInvoice,
            'pakets' => $pakets,
            'selectedLayananForModal' => $selectedLayananForModal,
        ]);
    }
}
