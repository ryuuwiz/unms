<?php

namespace App\Livewire\LayananPelanggan;

use App\Actions\LayananPelanggan\DaftarkanLayananAction;
use App\Enums\JenisTagihanPertama;
use App\Enums\PriceMode;
use App\Livewire\Concerns\HasSearchableOptions;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Perumahan;
use App\Models\Promo;
use App\Services\Billing\BillingService;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Data Registrasi Billing')]
class Create extends Component
{
    use HasSearchableOptions;

    // Step 1: Pilih pelanggan & paket
    public int $step = 1;

    /** Tiket Pemasangan yang menjadi asal registrasi ini (opsional). */
    #[Locked]
    public ?int $ticket_id = null;

    #[Locked]
    public ?int $pelanggan_id = null;

    public ?int $paket_layanan_id = null;

    // Step 2: Data layanan
    public string $nama_site = '';

    // Alamat pemasangan
    public string $alamat_sumber = 'utama';

    public ?int $perumahan_id = null;

    public string $alamat_pemasangan = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $tanggal_mulai = '';

    // Pengaturan Harga Layanan
    public string $price_mode = 'paket';

    public ?float $price_custom = null;

    // Tagihan pertama
    public string $jenis_tagihan_pertama = '';

    public ?int $promo_id = null;

    public string $kode_promo = '';

    public float $hargaPaket = 0.0;

    public float $diskonTagihanPertama = 0.0;

    public float $totalTagihanPertama = 0.0;

    public ?int $hariDitagih = null;

    public ?int $hariTotalPeriode = null;

    public string $tanggalJatuhTempoPertama = '';

    public function mount(Pelanggan $pelanggan): void
    {
        $this->authorize('create', LayananPelanggan::class);
        $this->tanggal_mulai = now()->toDateString();

        $this->pelanggan_id = $pelanggan->id;
        $this->syncAlamatDariPelanggan();
        $this->ticket_id = request()->filled('ticket_id') ? (int) request()->query('ticket_id') : null;
    }

    public function getPelangganProperty(): ?Pelanggan
    {
        return Pelanggan::with('perumahan')->find($this->pelanggan_id);
    }

    /**
     * Harga default paket terpilih (tanpa dipengaruhi mode harga custom) untuk ditampilkan
     * sebagai referensi "Harga Paket (Default)" di Step 1.
     */
    public function getPaketHargaDefaultProperty(): float
    {
        return (float) (PaketLayanan::find($this->paket_layanan_id)?->harga ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesStep1(): array
    {
        return [
            'paket_layanan_id' => ['required', 'integer', 'exists:paket_layanan,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesStep2(): array
    {
        return [
            'nama_site' => ['nullable', 'string', 'max:100'],
            'alamat_sumber' => ['required', 'in:utama,custom'],
            'perumahan_id' => ['nullable', 'integer', 'exists:perumahan,id'],
            'alamat_pemasangan' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'tanggal_mulai' => ['required', 'date'],
            'price_mode' => ['required', Rule::enum(PriceMode::class)],
            'price_custom' => [
                Rule::requiredIf(fn () => $this->price_mode === PriceMode::Custom->value),
                'nullable', 'numeric', 'min:1',
            ],
            'jenis_tagihan_pertama' => ['required', Rule::enum(JenisTagihanPertama::class)],
            'promo_id' => [
                Rule::requiredIf(fn () => $this->jenis_tagihan_pertama === JenisTagihanPertama::Promo->value),
                'nullable', 'integer', 'exists:promo,id',
            ],
        ];
    }

    public function nextStep(): void
    {
        $this->validate($this->rulesStep1(), [
            'pelanggan_id.required' => 'Pelanggan wajib dipilih.',
            'paket_layanan_id.required' => 'Paket layanan wajib dipilih.',
        ]);

        $this->step = 2;
        $this->recalculateTagihanPertama();
    }

    /**
     * Isi ulang alamat & koordinat pemasangan dari data alamat utama pelanggan.
     * Dipakai saat mount() dan saat staf memilih "Gunakan alamat utama pelanggan".
     */
    protected function syncAlamatDariPelanggan(): void
    {
        $pelanggan = $this->pelanggan;
        $this->alamat_pemasangan = $pelanggan?->alamat_lengkap ?? '';
        $this->latitude = $pelanggan?->latitude;
        $this->longitude = $pelanggan?->longitude;
        $this->perumahan_id = null;
    }

    /**
     * Toggle sumber alamat pemasangan: "utama" mengunci & mengisi otomatis dari data
     * pelanggan, "custom" mengosongkan field agar staf mengisi alamat site lain.
     *
     * Dipanggil otomatis oleh Livewire saat properti alamat_sumber berubah.
     */
    public function updatedAlamatSumber(): void
    {
        if ($this->alamat_sumber === 'utama') {
            $this->syncAlamatDariPelanggan();

            return;
        }

        $this->alamat_pemasangan = '';
        $this->latitude = null;
        $this->longitude = null;
        $this->perumahan_id = null;
    }

    /**
     * Tambahkan nama perumahan + kelurahan/kecamatan/kota ke alamat pemasangan, serta
     * koordinat perumahan jika tersedia. Hanya mengisi field yang masih kosong agar tidak
     * menimpa detail yang sudah diketik manual oleh staf.
     *
     * Dipanggil otomatis oleh Livewire saat properti perumahan_id berubah.
     */
    public function updatedPerumahanId(): void
    {
        if (! $this->perumahan_id) {
            return;
        }

        $perumahan = Perumahan::with('kelurahan.kecamatan.kota')->find($this->perumahan_id);
        if (! $perumahan) {
            return;
        }

        if (trim($this->alamat_pemasangan) === '') {
            $kelurahan = $perumahan->kelurahan;
            $kecamatan = $kelurahan?->kecamatan;
            $kota = $kecamatan?->kota;

            $this->alamat_pemasangan = collect([
                $perumahan->nama_perumahan,
                $kelurahan ? "Kel. {$kelurahan->nama_kelurahan}" : null,
                $kecamatan ? "Kec. {$kecamatan->nama_kecamatan}" : null,
                $kota?->nama_kota,
            ])->filter()->implode(', ');
        }

        if ($this->latitude === null && $this->longitude === null && $perumahan->latitude && $perumahan->longitude) {
            $this->latitude = (float) $perumahan->latitude;
            $this->longitude = (float) $perumahan->longitude;
        }
    }

    /**
     * Dipanggil otomatis oleh Livewire saat properti tanggal_mulai berubah -- proporsi hari
     * tagihan pertama bergantung pada tanggal ini.
     */
    public function updatedTanggalMulai(): void
    {
        $this->recalculateTagihanPertama();
    }

    /**
     * Reset harga custom saat staf beralih kembali ke mode harga paket (auto).
     *
     * Dipanggil otomatis oleh Livewire saat properti price_mode berubah.
     */
    public function updatedPriceMode(): void
    {
        if ($this->price_mode !== PriceMode::Custom->value) {
            $this->price_custom = null;
            $this->resetErrorBag('price_custom');
        }

        $this->recalculateTagihanPertama();
    }

    /**
     * Dipanggil otomatis oleh Livewire saat properti price_custom berubah.
     */
    public function updatedPriceCustom(): void
    {
        $this->recalculateTagihanPertama();
    }

    /**
     * Dipanggil otomatis oleh Livewire saat properti jenis_tagihan_pertama berubah. Reset
     * pilihan promo saat admin beralih menjauh dari opsi Promo.
     */
    public function updatedJenisTagihanPertama(): void
    {
        if ($this->jenis_tagihan_pertama !== JenisTagihanPertama::Promo->value) {
            $this->promo_id = null;
            $this->kode_promo = '';
            $this->resetErrorBag(['promo_id', 'kode_promo']);
        }

        $this->recalculateTagihanPertama();
    }

    /**
     * Dipanggil otomatis oleh Livewire saat properti promo_id berubah (dropdown promo).
     */
    public function updatedPromoId(): void
    {
        // Pilihan dropdown selalu menang atas kode yang diketik manual.
        $this->kode_promo = '';
        $this->recalculateTagihanPertama();
    }

    /**
     * Cocokkan kode promo yang diketik manual dengan promo aktif -- hanya dipakai jika
     * dropdown promo belum dipilih (lihat updatedPromoId()).
     */
    public function updatedKodePromo(): void
    {
        $this->resetErrorBag('kode_promo');

        $kode = trim($this->kode_promo);
        if ($kode === '') {
            $this->promo_id = null;
            $this->recalculateTagihanPertama();

            return;
        }

        $promo = Promo::findAktifByKode($kode);

        if (! $promo) {
            $this->promo_id = null;
            $this->addError('kode_promo', 'Kode promo tidak ditemukan atau sudah tidak aktif.');
            $this->recalculateTagihanPertama();

            return;
        }

        $this->promo_id = $promo->id;
        $this->recalculateTagihanPertama();
    }

    /**
     * Hitung ulang rincian harga tagihan pertama (dipakai oleh blok "Pengaturan Harga
     * Layanan" di bawah form) lewat BillingService::hitungRincianTagihanPertama() -- angka
     * yang sama persis dipakai lagi saat save() benar-benar menerbitkan invoice, sehingga
     * preview tidak pernah berbeda dari yang ditagihkan. Menghormati harga custom jika
     * price_mode = custom, persis seperti LayananPelanggan::hargaDasar().
     */
    public function recalculateTagihanPertama(): void
    {
        $paket = $this->paket_layanan_id ? PaketLayanan::find($this->paket_layanan_id) : null;
        $jenis = JenisTagihanPertama::tryFrom($this->jenis_tagihan_pertama);

        if (! $paket || ! $jenis || ! $this->tanggal_mulai) {
            $this->hargaPaket = (float) ($paket->harga ?? 0);
            $this->diskonTagihanPertama = 0.0;
            $this->totalTagihanPertama = 0.0;
            $this->hariDitagih = null;
            $this->hariTotalPeriode = null;

            return;
        }

        $promo = $this->promo_id ? Promo::find($this->promo_id) : null;
        $hargaOverride = $this->price_mode === PriceMode::Custom->value && $this->price_custom
            ? (float) $this->price_custom
            : null;

        $rincian = app(BillingService::class)->hitungRincianTagihanPertama(
            $paket,
            Carbon::parse($this->tanggal_mulai),
            $jenis,
            $promo,
            $hargaOverride,
        );

        $this->hargaPaket = $rincian['jumlah'];
        $this->diskonTagihanPertama = $rincian['diskon'];
        $this->totalTagihanPertama = $rincian['jumlah_setelah_promo'];
        $this->hariDitagih = $rincian['hari_ditagih'];
        $this->hariTotalPeriode = $rincian['hari_total_periode'];
        $this->tanggalJatuhTempoPertama = Carbon::parse($this->tanggal_mulai)->addDay()->toDateString();
    }

    public function prevStep(): void
    {
        $this->step = 1;
    }

    public function save(): void
    {
        $this->authorize('create', LayananPelanggan::class);
        $this->validate($this->rulesStep2(), [
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
            'price_custom.required' => 'Harga khusus wajib diisi saat memilih mode harga manual.',
        ]);

        $jenisTagihan = JenisTagihanPertama::from($this->jenis_tagihan_pertama);
        $promo = $jenisTagihan === JenisTagihanPertama::Promo && $this->promo_id
            ? Promo::find($this->promo_id)
            : null;

        try {
            app(DaftarkanLayananAction::class)->execute([
                'pelanggan_id' => $this->pelanggan_id,
                'paket_layanan_id' => $this->paket_layanan_id,
                'price_mode' => $this->price_mode,
                'price_custom' => $this->price_custom,
                'nama_site' => $this->nama_site,
                'alamat_pemasangan' => $this->alamat_pemasangan,
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'tanggal_mulai' => $this->tanggal_mulai,
                'jenis_tagihan_pertama' => $this->jenis_tagihan_pertama,
                'promo' => $promo,
                'ticket_id' => $this->ticket_id,
                'dibuat_oleh' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            report($e);
            $this->addError('jenis_tagihan_pertama', "Gagal membuat tagihan pertama: {$e->getMessage()}");

            return;
        }

        Flux::toast(
            variant: 'success',
            heading: 'Data Registrasi Billing Berhasil Dibuat',
            text: 'Layanan berstatus PROSES. Buat Ticket Pemasangan untuk melanjutkan instalasi & aktivasi PPP.',
            duration: 10000,
        );

        $this->redirectRoute('layanan-pelanggan.index', navigate: true);
    }

    /**
     * @return array<string, array{model: class-string, query: \Closure, label: \Closure, cap?: int}>
     */
    protected function searchableFields(): array
    {
        return [
            'paket_layanan_id' => [
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
        $jenisTagihanPertama = JenisTagihanPertama::cases();
        $promos = Promo::query()->aktif()->get();
        $perumahans = Perumahan::orderBy('nama_perumahan')->get();
        $priceModes = PriceMode::cases();

        return view('livewire.layanan-pelanggan.create', compact(
            'jenisTagihanPertama',
            'promos',
            'perumahans',
            'priceModes',
        ));
    }
}
