<?php

namespace App\Livewire\Pelanggan;

use App\Enums\StatusInvoice;
use App\Livewire\Concerns\HasSearchableOptions;
use App\Models\AkunPelanggan;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Pembayaran;
use App\Models\Promo;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\CustomerDocumentService;
use App\Services\Mikrotik\MikrotikService;
use Exception;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app')]
#[Title('Detail Pelanggan')]
class Show extends Component
{
    use HasSearchableOptions, WithFileUploads;

    public int $pelangganId;

    #[Url(as: 'tab')]
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

    public bool $showTambahInvoiceModal = false;

    public ?int $selectedLayananId = null;

    public ?int $newPaketId = null;

    public ?int $tambahInvoiceLayananId = null;

    public ?int $tambahInvoicePromoId = null;

    /** Layanan yang PPP Password-nya baru diungkap (di-reset saat navigate). */
    public ?int $revealedPppPasswordLayananId = null;

    public ?string $revealedPppPasswordValue = null;

    public string $tambahInvoiceKodePromo = '';

    public string $tambahInvoiceKeterangan = '';

    public ?int $tambahInvoiceJumlah = null;

    public string $tambahInvoiceTanggalJatuhTempo = '';

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

    public function mount(Pelanggan $pelanggan): void
    {
        $this->authorize('view', $pelanggan);
        $this->pelangganId = $pelanggan->id;
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

        $invoice = Invoice::where('pelanggan_id', $this->pelangganId)->findOrFail($this->selectedInvoiceId);

        $this->validate([
            'bayarMetode' => ['required', 'string', 'in:manual_admin,transfer'],
            'bayarJumlah' => ['required', 'numeric', Rule::in([(float) $invoice->jumlah_setelah_promo])],
            'bayarTanggal' => ['required', 'date'],
            'bayarReferensi' => ['nullable', 'string', 'max:100'],
            'bayarCatatan' => ['nullable', 'string', 'max:500'],
        ], [
            'bayarMetode.required' => 'Pilih metode pembayaran.',
            'bayarJumlah.required' => 'Jumlah pembayaran wajib diisi.',
            // Pembayaran manual wajib melunasi penuh -- lihat BillingService::prosesPembayaranManual().
            'bayarJumlah.in' => 'Nominal pembayaran harus sama persis dengan jumlah tagihan (Rp '.number_format((float) $invoice->jumlah_setelah_promo, 0, ',', '.').'). Sistem belum mendukung pembayaran sebagian.',
            'bayarTanggal.required' => 'Tanggal pembayaran wajib diisi.',
        ]);

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
            $this->loadPppStatuses($mikrotikService);

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

    /**
     * Paksa refresh status realtime PPP seluruh layanan pelanggan, melewati cache
     * dari MikrotikService::getPppStatus() (lihat loadPppStatuses()).
     */
    public function refreshPppStatus(MikrotikService $mikrotikService): void
    {
        $pelanggan = Pelanggan::with(['layanans.router'])->findOrFail($this->pelangganId);

        $this->pppStatuses = [];

        foreach ($pelanggan->layanans as $layanan) {
            if ($layanan->router && ! empty($layanan->ppp_username)) {
                $this->pppStatuses[$layanan->id] = $mikrotikService->refreshPppStatus(
                    $layanan->router,
                    $layanan->ppp_username
                );
            }
        }

        Flux::toast(
            text: 'Status realtime PPP berhasil diperbarui dari MikroTik.',
            variant: 'success',
        );
    }

    /**
     * Muat status realtime PPP per layanan dari MikroTik.
     *
     * Dipicu via wire:init (lazy, setelah render awal halaman) alih-alih di mount(),
     * supaya halaman tidak menunggu koneksi live ke RouterOS sebelum dirender.
     *
     * getPppStatus() tidak pernah melempar exception ke luar — kegagalan koneksi
     * per router sudah diisolasi & ditangkap di dalam MikrotikService::getPppStatus()
     * itu sendiri, yang selalu mengembalikan array dengan 'router_online' => false
     * dan 'error_message' terisi saat gagal. Jadi tidak perlu try/catch di sini.
     */
    public function loadPppStatuses(MikrotikService $mikrotikService): void
    {
        $pelanggan = Pelanggan::with(['layanans.router', 'layanans.paketLayanan.profilBandwidth'])
            ->findOrFail($this->pelangganId);

        $this->pppStatuses = [];

        foreach ($pelanggan->layanans as $layanan) {
            if ($layanan->router && ! empty($layanan->ppp_username)) {
                $this->pppStatuses[$layanan->id] = $mikrotikService->getPppStatus(
                    $layanan->router,
                    $layanan->ppp_username
                );
            }
        }
    }

    /**
     * Ungkap PPP Password (plaintext) layanan -- dibatasi Super Admin & beraudit trail.
     * Lihat CONTEXT.md "PPP Password Credential".
     */
    public function revealPppPassword(int $layananId): void
    {
        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        $layanan = $pelanggan->layanans()->findOrFail($layananId);
        $this->authorize('viewPppPassword', $layanan);

        activity('layanan_pelanggan')
            ->performedOn($layanan)
            ->causedBy(auth()->user())
            ->withProperties([
                'action' => 'reveal_ppp_password',
                'ip' => request()->ip(),
            ])
            ->log("Mengungkap PPP Password layanan {$layanan->ppp_username} milik pelanggan {$pelanggan->identitasLengkap()}");

        $this->revealedPppPasswordLayananId = $layanan->id;
        $this->revealedPppPasswordValue = $layanan->ppp_password_terenkripsi;
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
        $this->loadPppStatuses($mikrotikService);

        Flux::toast(
            variant: 'success',
            text: "Paket berhasil diubah ke {$newPaket->nama_paket}. Job sinkronisasi profil ke MikroTik telah dikirim ke antrean."
        );
    }

    public function openTambahInvoiceModal(): void
    {
        $this->authorize('create', Invoice::class);

        $this->tambahInvoiceLayananId = null;
        $this->tambahInvoicePromoId = null;
        $this->tambahInvoiceKodePromo = '';
        $this->tambahInvoiceKeterangan = '';
        $this->tambahInvoiceJumlah = null;
        $this->tambahInvoiceTanggalJatuhTempo = Carbon::today()->addDays(7)->toDateString();
        $this->resetErrorBag();
        $this->showTambahInvoiceModal = true;
    }

    public function closeTambahInvoiceModal(): void
    {
        $this->showTambahInvoiceModal = false;
    }

    public function updatedTambahInvoicePromoId(): void
    {
        // Pilihan dropdown selalu menang atas kode yang diketik manual.
        $this->tambahInvoiceKodePromo = '';
    }

    /**
     * Cocokkan kode promo yang diketik manual dengan promo aktif -- lihat Promo::findAktifByKode()
     * (juga dipakai Invoice\Create untuk alur tambah invoice manual full-page).
     */
    public function updatedTambahInvoiceKodePromo(): void
    {
        $this->resetErrorBag('tambahInvoiceKodePromo');

        $kode = trim($this->tambahInvoiceKodePromo);
        if ($kode === '') {
            $this->tambahInvoicePromoId = null;

            return;
        }

        $promo = Promo::findAktifByKode($kode);

        if (! $promo) {
            $this->tambahInvoicePromoId = null;
            $this->addError('tambahInvoiceKodePromo', 'Kode promo tidak ditemukan atau sudah tidak aktif.');

            return;
        }

        $this->tambahInvoicePromoId = $promo->id;
    }

    public function simpanTambahInvoice(BillingService $billingService): void
    {
        $this->authorize('create', Invoice::class);

        $this->validate([
            'tambahInvoiceLayananId' => ['required', 'integer', 'exists:layanan_pelanggan,id'],
            'tambahInvoicePromoId' => ['nullable', 'integer', 'exists:promo,id'],
            'tambahInvoiceKeterangan' => ['required', 'string', 'max:500'],
            'tambahInvoiceJumlah' => ['required', 'integer', 'min:1'],
            'tambahInvoiceTanggalJatuhTempo' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'tambahInvoiceLayananId.required' => 'Layanan terkait wajib dipilih.',
            'tambahInvoiceKeterangan.required' => 'Keterangan invoice wajib diisi.',
            'tambahInvoiceJumlah.required' => 'Total jumlah wajib diisi.',
            'tambahInvoiceJumlah.integer' => 'Total jumlah hanya boleh berupa angka, tanpa titik/koma.',
            'tambahInvoiceJumlah.min' => 'Total jumlah harus lebih dari 0.',
            'tambahInvoiceTanggalJatuhTempo.required' => 'Tanggal jatuh tempo wajib diisi.',
            'tambahInvoiceTanggalJatuhTempo.after_or_equal' => 'Tanggal jatuh tempo tidak boleh kurang dari hari ini.',
        ]);

        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        // Guard kepemilikan: layanan harus benar-benar milik pelanggan ini, sama seperti
        // prosesUbahPaket() -- bukan sekadar `exists:layanan_pelanggan,id` yang lolos untuk ID apa pun.
        $layanan = $pelanggan->layanans()->findOrFail($this->tambahInvoiceLayananId);
        $promo = $this->tambahInvoicePromoId ? Promo::find($this->tambahInvoicePromoId) : null;

        $invoice = $billingService->generateManualInvoice(
            layanan: $layanan,
            jumlah: (float) $this->tambahInvoiceJumlah,
            keterangan: $this->tambahInvoiceKeterangan,
            dibuatOleh: auth()->id(),
            promo: $promo,
            tanggalJatuhTempo: Carbon::parse($this->tambahInvoiceTanggalJatuhTempo),
        );

        $this->closeTambahInvoiceModal();

        Flux::toast(variant: 'success', text: "Invoice {$invoice->no_invoice} berhasil diterbitkan.");
    }

    /**
     * @return array<string, array{model: class-string, query: \Closure, label: \Closure, cap?: int}>
     */
    protected function searchableFields(): array
    {
        return [
            'newPaketId' => [
                'model' => PaketLayanan::class,
                'query' => fn () => PaketLayanan::aktif()->with('profilBandwidth'),
                'label' => fn (PaketLayanan $pk) => $pk->nama_paket.' — '.$pk->formattedHarga()
                    .' ('.($pk->profilBandwidth?->labelKecepatan() ?? 'No Profile').')',
                'cap' => 20,
            ],
        ];
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
            'layanans.ipPubliks',
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

        $selectedLayananForModal = $this->selectedLayananId
            ? $pelanggan->layanans->firstWhere('id', $this->selectedLayananId)
            : null;

        $promosAktif = Promo::query()->aktif()->get();

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
            'selectedLayananForModal' => $selectedLayananForModal,
            'promosAktif' => $promosAktif,
        ]);
    }
}
