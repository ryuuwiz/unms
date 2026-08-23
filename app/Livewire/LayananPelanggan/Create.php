<?php

namespace App\Livewire\LayananPelanggan;

use App\Enums\JenisKoneksi;
use App\Enums\MikrotikJobStatus;
use App\Enums\MikrotikJobType;
use App\Enums\ProvisioningStatus;
use App\Enums\StatusLayanan;
use App\Jobs\Mikrotik\ProvisionPppoeAccountJob;
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
#[Title('Tambah Layanan Pelanggan')]
class Create extends Component
{
    // Step 1: Pilih pelanggan & paket
    public int $step = 1;

    public ?int $pelanggan_id = null;

    public ?int $paket_layanan_id = null;

    // Step 2: Konfigurasi koneksi
    public ?int $router_id = null;

    public string $ppp_username = '';

    public string $ppp_password = '';

    public string $jenis_koneksi = 'pppoe';

    public string $tanggal_mulai = '';

    public bool $auto_provision = true;

    public function mount(): void
    {
        $this->authorize('create', LayananPelanggan::class);
        $this->tanggal_mulai = now()->toDateString();
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
        return [
            'router_id' => ['required', 'integer', 'exists:router,id'],
            'ppp_username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/', 'unique:layanan_pelanggan,ppp_username'],
            'ppp_password' => ['required', 'string', 'min:4', 'max:64'],
            'jenis_koneksi' => ['required', 'string', 'in:pppoe,ip_static'],
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

        $this->step = 2;
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
            'ppp_username.required' => 'Username PPP wajib diisi.',
            'ppp_username.min' => 'Username PPP minimal 3 karakter.',
            'ppp_username.max' => 'Username PPP maksimal 64 karakter.',
            'ppp_username.regex' => 'Username PPP hanya boleh berisi huruf, angka, titik (.), strip (-), dan underscore (_).',
            'ppp_username.unique' => 'Username PPP sudah digunakan.',
            'ppp_password.required' => 'Password PPP wajib diisi.',
            'ppp_password.min' => 'Password PPP minimal 4 karakter.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
        ]);

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

                Flux::toast(variant: 'success', text: "Layanan {$layanan->ppp_username} berhasil didaftarkan dan langsung terprovisi aktif di {$router->nama_router}.");
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

                Flux::toast(variant: 'warning', text: "Layanan didaftarkan. Provisi ke router tertunda: {$e->getMessage()}");
            }
        } else {
            Flux::toast(variant: 'success', text: 'Layanan pelanggan berhasil didaftarkan.');
        }

        $this->redirectRoute('layanan-pelanggan.index', navigate: true);
    }

    public function render(): View
    {
        $pelanggans = Pelanggan::aktif()->orderBy('nama_depan')->get();
        $pakets = PaketLayanan::aktif()->with('profilBandwidth')->orderBy('nama_paket')->get();
        $routers = Router::online()->get();
        $jenisKoneksi = JenisKoneksi::cases();

        return view('livewire.layanan-pelanggan.create', compact(
            'pelanggans',
            'pakets',
            'routers',
            'jenisKoneksi',
        ));
    }
}
