<?php

namespace App\Livewire\LayananPelanggan;

use App\Enums\JenisKoneksi;
use App\Enums\JenisTagihanPertama;
use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Livewire\Concerns\HasSearchableOptions;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Promo;
use App\Models\Router;
use App\Models\Ticket;
use App\Services\Billing\BillingService;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    /** Tiket Pemasangan selesai yang menjadi asal registrasi ini (opsional). */
    #[Locked]
    public ?int $ticket_id = null;

    public ?int $pelanggan_id = null;

    public ?int $paket_layanan_id = null;

    // Step 2: Konfigurasi koneksi
    public ?int $router_id = null;

    public ?int $ip_pool_id = null;

    public ?string $ip_static = null;

    public string $nama_site = '';

    public string $alamat_pemasangan = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $ppp_username = '';

    public string $jenis_koneksi = 'pppoe';

    public string $tanggal_mulai = '';

    public bool $auto_provision = true;

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

    public function mount(): void
    {
        $this->authorize('create', LayananPelanggan::class);
        $this->tanggal_mulai = now()->toDateString();

        if (request()->filled('pelanggan_id')) {
            $this->pelanggan_id = (int) request()->query('pelanggan_id');
            $this->updatedPelangganId();
            $this->ticket_id = request()->filled('ticket_id') ? (int) request()->query('ticket_id') : null;
        }

        $this->initSingleRouterSelection();
    }

    /**
     * Auto-assign router_id jika hanya ada 1 Router terdaftar di sistem,
     * serta trigger pemuatan dan auto-selection IP Pool otomatis.
     */
    protected function initSingleRouterSelection(): void
    {
        if ($this->router_id) {
            return;
        }

        $routers = Router::get(['id']);
        if ($routers->count() === 1) {
            $this->router_id = $routers->first()->id;
            $this->updatedRouterId();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesStep1(): array
    {
        return [
            'pelanggan_id' => ['required', 'integer', 'exists:pelanggan,id'],
            'paket_layanan_id' => ['required', 'integer', 'exists:paket_layanan,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function rulesStep2(): array
    {
        $pelanggan = $this->pelanggan_id ? Pelanggan::find($this->pelanggan_id) : null;
        $escapedNoReg = $pelanggan ? preg_quote($pelanggan->no_reg, '/') : '[A-Za-z0-9]+';

        return [
            'router_id' => ['required', 'integer', 'exists:router,id'],
            'jenis_koneksi' => ['required', 'string', 'in:pppoe,ip_static'],
            'nama_site' => ['nullable', 'string', 'max:100'],
            'alamat_pemasangan' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'ip_pool_id' => $this->jenis_koneksi === 'pppoe'
                ? ['required', 'integer', Rule::exists('ip_pool', 'id')->where('router_id', $this->router_id)]
                : ['nullable', 'integer', Rule::exists('ip_pool', 'id')->where('router_id', $this->router_id)],
            'ip_static' => $this->jenis_koneksi === 'ip_static'
                ? ['required', 'ipv4']
                : ['nullable', 'ipv4'],
            'ppp_username' => [
                'required',
                'string',
                'max:64',
                'regex:/^'.$escapedNoReg.'_[0-9]{5}$/',
                'unique:layanan_pelanggan,ppp_username',
            ],
            'tanggal_mulai' => ['required', 'date'],
            'auto_provision' => ['boolean'],
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

        $this->initSingleRouterSelection();
        $this->step = 2;
        $this->recalculateTagihanPertama();
    }

    /**
     * Auto-fill ppp_username saat pelanggan dipilih di Step 1.
     *
     * Dipanggil otomatis oleh Livewire saat properti pelanggan_id berubah.
     */
    public function updatedPelangganId(): void
    {
        if ($this->pelanggan_id) {
            $pelanggan = Pelanggan::find($this->pelanggan_id);
            if ($pelanggan) {
                $this->ppp_username = LayananPelanggan::generatePppUsername($pelanggan);
            }
        } else {
            $this->ppp_username = '';
        }
    }

    /**
     * Auto-assign ip_pool_id jika router hanya memiliki 1 pool,
     * atau reset ke null jika memiliki banyak/tanpa pool.
     *
     * Dipanggil otomatis oleh Livewire saat properti router_id berubah.
     */
    public function updatedRouterId(): void
    {
        if ($this->router_id) {
            $pools = IpPool::where('router_id', $this->router_id)->get(['id']);
            if ($pools->count() === 1) {
                $this->ip_pool_id = $pools->first()->id;
            } else {
                $this->ip_pool_id = null;
            }
        } else {
            $this->ip_pool_id = null;
        }
    }

    /**
     * Sesuaikan ketersediaan field IP Pool vs IP Statis saat jenis koneksi berubah.
     *
     * Dipanggil otomatis oleh Livewire saat properti jenis_koneksi berubah.
     */
    public function updatedJenisKoneksi(): void
    {
        if ($this->jenis_koneksi === 'pppoe') {
            $this->ip_static = null;
            if ($this->router_id) {
                $pools = IpPool::where('router_id', $this->router_id)->get(['id']);
                if ($pools->count() === 1) {
                    $this->ip_pool_id = $pools->first()->id;
                }
            }
        } else {
            $this->ip_pool_id = null;
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
     * preview tidak pernah berbeda dari yang ditagihkan.
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
        $rincian = app(BillingService::class)->hitungRincianTagihanPertama(
            $paket,
            Carbon::parse($this->tanggal_mulai),
            $jenis,
            $promo,
        );

        $this->hargaPaket = $rincian['jumlah'];
        $this->diskonTagihanPertama = $rincian['diskon'];
        $this->totalTagihanPertama = $rincian['jumlah_setelah_promo'];
        $this->hariDitagih = $rincian['hari_ditagih'];
        $this->hariTotalPeriode = $rincian['hari_total_periode'];
        $this->tanggalJatuhTempoPertama = $this->tanggal_mulai;
    }

    public function prevStep(): void
    {
        $this->step = 1;
    }

    public function save(): void
    {
        $this->authorize('create', LayananPelanggan::class);
        $this->validate($this->rulesStep2(), [
            'router_id.required' => 'Router wajib dipilih.',
            'router_id.exists' => 'Router yang dipilih tidak valid.',
            'ip_pool_id.required' => 'IP Pool wajib dipilih untuk koneksi PPPoE.',
            'ip_pool_id.exists' => 'IP Pool yang dipilih tidak valid atau tidak terdaftar pada router terpilih.',
            'ip_static.required' => 'Alamat IP Statis wajib diisi untuk koneksi IP Static.',
            'ip_static.ipv4' => 'Format Alamat IP Statis tidak valid (contoh: 192.168.1.50).',
            'ppp_username.required' => 'Username PPP wajib diisi.',
            'ppp_username.max' => 'Username PPP maksimal 64 karakter.',
            'ppp_username.regex' => 'Format username PPP tidak valid. Harus berupa No.Reg pelanggan diikuti underscore dan 5 digit angka (contoh: BF2308202601_00001).',
            'ppp_username.unique' => 'Username PPP sudah digunakan.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
        ]);

        // Cek duplikasi: pelanggan tidak boleh memiliki layanan aktif/proses/suspend dengan router & paket yang persis sama
        $existingDuplicate = LayananPelanggan::where('pelanggan_id', $this->pelanggan_id)
            ->where('router_id', $this->router_id)
            ->where('paket_layanan_id', $this->paket_layanan_id)
            ->whereIn('status', [StatusLayanan::Aktif, StatusLayanan::Proses, StatusLayanan::Suspend])
            ->exists();

        if ($existingDuplicate) {
            $this->addError('router_id', 'Pelanggan ini sudah memiliki Data Registrasi Billing aktif dengan paket yang sama pada router ini. Untuk pemasangan site baru, gunakan paket atau router yang sesuai.');

            return;
        }

        /** @var PaketLayanan $paket */
        $paket = PaketLayanan::findOrFail($this->paket_layanan_id);

        // Hitung tanggal expired berdasarkan masa aktif paket
        $mulai = Carbon::parse($this->tanggal_mulai);
        $expired = $paket->masa_aktif_satuan->value === 'bulan'
            ? $mulai->copy()->addMonths($paket->masa_aktif_nilai)
            : $mulai->copy()->addDays($paket->masa_aktif_nilai);

        // Password PPP selalu di-generate sistem (tidak pernah diinput manual staf) --
        // lihat CONTEXT.md "PPP Password Credential". Ditampilkan sekali lewat toast di bawah.
        $pppPassword = Str::password(8, symbols: false);

        try {
            $layanan = DB::transaction(function () use ($expired, $pppPassword) {
                $layanan = LayananPelanggan::create([
                    'pelanggan_id' => $this->pelanggan_id,
                    'paket_layanan_id' => $this->paket_layanan_id,
                    'router_id' => $this->router_id,
                    'nama_site' => $this->nama_site ?: null,
                    'alamat_pemasangan' => $this->alamat_pemasangan ?: null,
                    'latitude' => $this->latitude,
                    'longitude' => $this->longitude,
                    'ip_pool_id' => $this->jenis_koneksi === 'pppoe' ? $this->ip_pool_id : null,
                    'ip_static' => $this->jenis_koneksi === 'ip_static' ? $this->ip_static : null,
                    'ppp_username' => $this->ppp_username,
                    'ppp_password_terenkripsi' => $pppPassword,
                    'jenis_koneksi' => $this->jenis_koneksi,
                    'status' => StatusLayanan::Proses,
                    'provisioning_status' => ProvisioningStatus::Pending,
                    'tanggal_mulai' => $this->tanggal_mulai,
                    'tanggal_expired' => $expired->toDateString(),
                ]);

                $jenisTagihan = JenisTagihanPertama::from($this->jenis_tagihan_pertama);
                $promo = $jenisTagihan === JenisTagihanPertama::Promo && $this->promo_id
                    ? Promo::find($this->promo_id)
                    : null;

                app(BillingService::class)->generateFirstInvoice(
                    layanan: $layanan,
                    jenis: $jenisTagihan,
                    dibuatOleh: auth()->id(),
                    promo: $promo,
                );

                return $layanan;
            });
        } catch (\Throwable $e) {
            report($e);
            $this->addError('jenis_tagihan_pertama', "Gagal membuat tagihan pertama: {$e->getMessage()}");

            return;
        }

        if ($this->ticket_id) {
            Ticket::whereKey($this->ticket_id)
                ->where('pelanggan_id', $this->pelanggan_id)
                ->where('perlu_aktivasi_manual', true)
                ->update(['layanan_pelanggan_id' => $layanan->id, 'perlu_aktivasi_manual' => false]);
        }

        if ($this->auto_provision) {
            try {
                $mikrotikService = app(MikrotikService::class);
                $router = Router::findOrFail($this->router_id);
                $mikrotikService->createOrUpdatePppoeSecret($router, $layanan);

                $layanan->update([
                    'status' => StatusLayanan::Aktif,
                ]);

                MikrotikJobLog::create([
                    'router_id' => $this->router_id,
                    'layanan_pelanggan_id' => $layanan->id,
                    'job_type' => MikrotikJobType::ProvisionPppoe,
                    'status' => MikrotikJobStatus::Success,
                    'attempt_count' => 1,
                    'finished_at' => now(),
                ]);

                Flux::toast(
                    variant: 'success',
                    heading: 'Data Registrasi Billing Berhasil & Terprovisi',
                    text: "{$layanan->ppp_username} aktif di {$router->nama_router}. PPP Password: {$pppPassword} — salin sekarang, tidak akan ditampilkan lagi kecuali oleh Super Admin.",
                    duration: 30000,
                );
            } catch (\Throwable $e) {
                // Kegagalan sudah tercatat pada layanan (provisioning_status = Failed +
                // last_provisioning_error) oleh MikrotikService; tanpa retry buta karena
                // galat konfigurasi tidak akan berhasil dengan mencoba ulang -- admin
                // memperbaiki data lalu memakai tombol Provisi di daftar layanan.
                MikrotikJobLog::create([
                    'router_id' => $this->router_id,
                    'layanan_pelanggan_id' => $layanan->id,
                    'job_type' => MikrotikJobType::ProvisionPppoe,
                    'status' => MikrotikJobStatus::Failed,
                    'attempt_count' => 1,
                    'error_message' => $e->getMessage(),
                    'finished_at' => now(),
                ]);

                Flux::toast(
                    variant: 'warning',
                    heading: 'Data Registrasi Billing Dibuat, Provisi Gagal',
                    text: "Provisi ke router gagal: {$e->getMessage()} Perbaiki data lalu gunakan tombol Provisi di daftar layanan. PPP Password: {$pppPassword} — salin sekarang, tidak akan ditampilkan lagi kecuali oleh Super Admin.",
                    duration: 30000,
                );
            }
        } else {
            Flux::toast(
                variant: 'success',
                heading: 'Data Registrasi Billing Berhasil Dibuat',
                text: "PPP Password: {$pppPassword} — salin sekarang, tidak akan ditampilkan lagi kecuali oleh Super Admin.",
                duration: 30000,
            );
        }

        $this->redirectRoute('layanan-pelanggan.index', navigate: true);
    }

    /**
     * @return array<string, array{model: class-string, query: \Closure, label: \Closure, cap?: int}>
     */
    protected function searchableFields(): array
    {
        return [
            'pelanggan_id' => [
                'model' => Pelanggan::class,
                'query' => fn () => Pelanggan::query(),
                'label' => fn (Pelanggan $p) => $p->labelSelector(),
                'cap' => 20,
            ],
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
        $routers = Router::orderBy('nama_router')->get();
        $ipPools = $this->router_id
            ? IpPool::where('router_id', $this->router_id)->orderBy('nama_pool')->get()
            : collect();
        $jenisKoneksi = JenisKoneksi::cases();
        $jenisTagihanPertama = JenisTagihanPertama::cases();
        $promos = Promo::query()->aktif()->get();

        return view('livewire.layanan-pelanggan.create', compact(
            'routers',
            'ipPools',
            'jenisKoneksi',
            'jenisTagihanPertama',
            'promos',
        ));
    }
}
