<?php

namespace App\Livewire\LayananPelanggan;

use App\Enums\JenisKoneksi;
use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\MikrotikJobLog;
use App\Models\PaketLayanan;
use App\Models\Pelanggan;
use App\Models\Router;
use App\Services\Mikrotik\MikrotikService;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Data Registrasi Billing')]
class Create extends Component
{
    // Step 1: Pilih pelanggan & paket
    public int $step = 1;

    public ?int $pelanggan_id = null;

    public ?int $paket_layanan_id = null;

    // Step 2: Konfigurasi koneksi
    public ?int $router_id = null;

    public ?int $ip_pool_id = null;

    public ?string $ip_static = null;

    public string $ppp_username = '';

    public string $ppp_password = '';

    public string $jenis_koneksi = 'pppoe';

    public string $tanggal_mulai = '';

    public bool $auto_provision = true;

    public function mount(): void
    {
        $this->authorize('create', LayananPelanggan::class);
        $this->tanggal_mulai = now()->toDateString();
        $this->initSingleRouterSelection();
    }

    /**
     * Auto-assign router_id jika hanya ada 1 Router Online di sistem,
     * serta trigger pemuatan dan auto-selection IP Pool otomatis.
     */
    protected function initSingleRouterSelection(): void
    {
        if ($this->router_id) {
            return;
        }

        $onlineRouters = Router::online()->get(['id']);
        if ($onlineRouters->count() === 1) {
            $this->router_id = $onlineRouters->first()->id;
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
            'ip_pool_id' => $this->jenis_koneksi === 'pppoe'
                ? ['required', 'integer', 'exists:ip_pool,id']
                : ['nullable', 'integer', 'exists:ip_pool,id'],
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
            'ppp_password' => ['required', 'string', 'min:4', 'max:64'],
            'tanggal_mulai' => ['required', 'date'],
            'auto_provision' => ['boolean'],
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
            'ip_pool_id.exists' => 'IP Pool yang dipilih tidak valid.',
            'ip_static.required' => 'Alamat IP Statis wajib diisi untuk koneksi IP Static.',
            'ip_static.ipv4' => 'Format Alamat IP Statis tidak valid (contoh: 192.168.1.50).',
            'ppp_username.required' => 'Username PPP wajib diisi.',
            'ppp_username.max' => 'Username PPP maksimal 64 karakter.',
            'ppp_username.regex' => 'Format username PPP tidak valid. Harus berupa No.Reg pelanggan diikuti underscore dan 5 digit angka (contoh: BF2308202601_00001).',
            'ppp_username.unique' => 'Username PPP sudah digunakan.',
            'ppp_password.required' => 'Password PPP wajib diisi.',
            'ppp_password.min' => 'Password PPP minimal 4 karakter.',
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

        $layanan = LayananPelanggan::create([
            'pelanggan_id' => $this->pelanggan_id,
            'paket_layanan_id' => $this->paket_layanan_id,
            'router_id' => $this->router_id,
            'ip_pool_id' => $this->jenis_koneksi === 'pppoe' ? $this->ip_pool_id : null,
            'ip_static' => $this->jenis_koneksi === 'ip_static' ? $this->ip_static : null,
            'ppp_username' => $this->ppp_username,
            'ppp_password_terenkripsi' => $this->ppp_password,
            'jenis_koneksi' => $this->jenis_koneksi,
            'status' => StatusLayanan::Proses,
            'provisioning_status' => ProvisioningStatus::Pending,
            'tanggal_mulai' => $this->tanggal_mulai,
            'tanggal_expired' => $expired->toDateString(),
        ]);

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

                Flux::toast(variant: 'success', text: "Data Registrasi Billing {$layanan->ppp_username} berhasil didaftarkan dan langsung terprovisi aktif di {$router->nama_router}.");
            } catch (\Throwable $e) {
                ProvisionPppoeAccountJob::dispatch($layanan);

                MikrotikJobLog::create([
                    'router_id' => $this->router_id,
                    'layanan_pelanggan_id' => $layanan->id,
                    'job_type' => MikrotikJobType::ProvisionPppoe,
                    'status' => MikrotikJobStatus::Failed,
                    'attempt_count' => 1,
                    'error_message' => $e->getMessage(),
                    'finished_at' => now(),
                ]);

                Flux::toast(variant: 'warning', text: "Data Registrasi Billing didaftarkan. Provisi ke router tertunda: {$e->getMessage()}");
            }
        } else {
            Flux::toast(variant: 'success', text: 'Data Registrasi Billing berhasil didaftarkan.');
        }

        $this->redirectRoute('layanan-pelanggan.index', navigate: true);
    }

    public function render(): View
    {
        $pelanggans = Pelanggan::aktif()->orderBy('nama_depan')->get();
        $pakets = PaketLayanan::aktif()->with('profilBandwidth')->orderBy('nama_paket')->get();
        $routers = Router::online()->get();
        $ipPools = $this->router_id
            ? IpPool::where('router_id', $this->router_id)->orderBy('nama_pool')->get()
            : collect();
        $jenisKoneksi = JenisKoneksi::cases();

        return view('livewire.layanan-pelanggan.create', compact(
            'pelanggans',
            'pakets',
            'routers',
            'ipPools',
            'jenisKoneksi',
        ));
    }
}
