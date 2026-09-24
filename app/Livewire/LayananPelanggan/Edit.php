<?php

namespace App\Livewire\LayananPelanggan;

use App\Actions\IpPublik\LepasIpPublikAction;
use App\Actions\IpPublik\TetapkanIpPublikAction;
use App\Actions\LayananPelanggan\UbahStatusLayananAction;
use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Livewire\Concerns\HasSearchableOptions;
use App\Models\IpPool;
use App\Models\IpPublik;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Router;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Data Registrasi Billing')]
class Edit extends Component
{
    use HasSearchableOptions;

    #[Locked]
    public int $layananId;

    #[Locked]
    public string $pelangganNoReg = '';

    public ?int $paket_layanan_id = null;

    public ?int $router_id = null;

    public ?int $ip_pool_id = null;

    public ?string $ip_static = null;

    public ?int $ip_publik_id = null;

    public string $nama_site = '';

    public string $alamat_pemasangan = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $ppp_username = '';

    /** Holds the generated PPP password after a reset, cleared on navigate. */
    public string $generatedPppPassword = '';

    public string $jenis_koneksi = 'pppoe';

    public string $status = 'aktif';

    public string $tanggal_mulai = '';

    public string $tanggal_expired = '';

    public function mount(LayananPelanggan $layananPelanggan): void
    {
        $this->authorize('update', $layananPelanggan);

        $this->layananId = $layananPelanggan->id;
        $this->pelangganNoReg = $layananPelanggan->pelanggan->no_reg;
        $this->paket_layanan_id = $layananPelanggan->paket_layanan_id;
        $this->router_id = $layananPelanggan->router_id;
        $this->ip_pool_id = $layananPelanggan->ip_pool_id;
        $this->ip_static = $layananPelanggan->ip_static;
        $this->ip_publik_id = $layananPelanggan->ipPubliks()->value('id');
        $this->nama_site = $layananPelanggan->nama_site ?? '';
        $this->alamat_pemasangan = $layananPelanggan->alamat_pemasangan ?? '';
        $this->latitude = $layananPelanggan->latitude;
        $this->longitude = $layananPelanggan->longitude;
        $this->ppp_username = $layananPelanggan->ppp_username;
        $this->jenis_koneksi = $layananPelanggan->jenis_koneksi->value;
        $this->status = $layananPelanggan->status->value;
        $this->tanggal_mulai = $layananPelanggan->tanggal_mulai->toDateString();
        $this->tanggal_expired = $layananPelanggan->tanggal_expired?->toDateString() ?? '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $escapedNoReg = preg_quote($this->pelangganNoReg, '/');

        return [
            'paket_layanan_id' => ['required', 'integer', 'exists:paket_layanan,id'],
            'router_id' => ['required', 'integer', 'exists:router,id'],
            'jenis_koneksi' => ['required', 'string', 'in:pppoe,ip_static'],
            'nama_site' => ['nullable', 'string', 'max:100'],
            'alamat_pemasangan' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'ip_pool_id' => $this->jenis_koneksi === 'pppoe'
                ? ['required', 'integer', Rule::exists('ip_pool', 'id')->where('router_id', $this->router_id)]
                : ['nullable', 'integer', Rule::exists('ip_pool', 'id')->where('router_id', $this->router_id)],
            'ip_static' => [
                $this->jenis_koneksi === 'ip_static' ? 'required' : 'nullable',
                'ipv4',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $bentrok = $value && $this->router_id ? IpPublik::bentrokDenganPool($this->router_id, (string) $value) : null;
                    if ($bentrok !== null) {
                        $fail($bentrok);
                    }

                    if ($value && (
                        LayananPelanggan::where('router_id', $this->router_id)->where('ip_static', $value)->whereKeyNot($this->layananId)->exists()
                        || IpPublik::where('alamat_ip', $value)->exists()
                    )) {
                        $fail("Alamat {$value} sudah dipakai layanan lain atau terdaftar sebagai IP Publik pada router yang sama.");
                    }
                },
            ],
            'ip_publik_id' => ['nullable', 'integer', Rule::exists('ip_publik', 'id')->where('router_id', $this->router_id)],
            'ppp_username' => [
                'required',
                'string',
                'max:64',
                'regex:/^'.$escapedNoReg.'_[0-9]{5}$/',
                "unique:layanan_pelanggan,ppp_username,{$this->layananId}",
            ],
            'status' => ['required', Rule::enum(StatusLayanan::class)],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_expired' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ];
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
        if ($this->jenis_koneksi !== 'pppoe') {
            $this->ip_publik_id = null;
        }

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

    public function save(): void
    {
        $layanan = LayananPelanggan::findOrFail($this->layananId);
        $this->authorize('update', $layanan);
        $this->validate($this->rules(), [
            'router_id.required' => 'Router wajib dipilih.',
            'router_id.exists' => 'Router yang dipilih tidak valid.',
            'ip_pool_id.required' => 'IP Pool wajib dipilih untuk koneksi PPPoE.',
            'ip_pool_id.exists' => 'IP Pool yang dipilih tidak valid atau tidak terdaftar pada router terpilih.',
            'ip_static.required' => 'Alamat IP Statis wajib diisi untuk koneksi IP Static.',
            'ip_static.ipv4' => 'Format Alamat IP Statis tidak valid (contoh: 192.168.1.50).',
            'ppp_username.required' => 'Username PPP wajib diisi.',
            'ppp_username.regex' => 'Format username PPP tidak valid. Harus berupa No.Reg pelanggan diikuti underscore dan 5 digit angka (contoh: BF2308202601_00001).',
            'ppp_username.max' => 'Username PPP maksimal 64 karakter.',
            'tanggal_mulai.required' => 'Tanggal mulai wajib diisi.',
        ]);

        $data = [
            'paket_layanan_id' => $this->paket_layanan_id,
            'router_id' => $this->router_id,
            'nama_site' => $this->nama_site ?: null,
            'alamat_pemasangan' => $this->alamat_pemasangan ?: null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'ip_pool_id' => $this->jenis_koneksi === 'pppoe' ? $this->ip_pool_id : null,
            'ip_static' => $this->jenis_koneksi === 'ip_static' ? $this->ip_static : null,
            'ppp_username' => $this->ppp_username,
            'jenis_koneksi' => $this->jenis_koneksi,
            'tanggal_mulai' => $this->tanggal_mulai,
            'tanggal_expired' => $this->tanggal_expired ?: null,
        ];

        DB::transaction(function () use ($layanan, $data) {
            $layanan->update($data);
            $this->sinkronkanIpPublik($layanan);
        });

        // Perubahan status wajib lewat Action agar event (isolir/aktivasi/hapus secret) dan jejak actor ikut jalan;
        // update langsung membuat status billing dan router tidak sinkron.
        $statusBaru = StatusLayanan::from($this->status);
        if ($layanan->fresh()->status !== $statusBaru) {
            app(UbahStatusLayananAction::class)->execute($layanan->fresh(), $statusBaru, Auth::guard('web')->user(), 'Diubah lewat form Edit Data Registrasi Billing');
        }

        Flux::toast(variant: 'success', text: 'Data Registrasi Billing berhasil diperbarui.');
        $this->redirectRoute('layanan-pelanggan.index', navigate: true);
    }

    /**
     * Selaraskan IP Publik layanan dengan pilihan form. IP lama dilepas bila diganti/dikosongkan atau
     * router berubah (IP terikat router); provisi ulang + putus sesi dipicu action, bukan form.
     */
    private function sinkronkanIpPublik(LayananPelanggan $layanan): void
    {
        $tujuanId = $this->jenis_koneksi === 'pppoe' ? $this->ip_publik_id : null;
        $saatIni = $layanan->ipPubliks()->first();
        $routerBerubah = $layanan->wasChanged('router_id');

        if ($saatIni && ($routerBerubah || $saatIni->id !== $tujuanId)) {
            app(LepasIpPublikAction::class)->execute($saatIni, reprovision: ! $routerBerubah && $tujuanId === null);
            $saatIni = null;
        }

        if ($tujuanId && $saatIni === null) {
            app(TetapkanIpPublikAction::class)->execute(IpPublik::findOrFail($tujuanId), $layanan->fresh());
        }
    }

    /**
     * Generate PPP Password baru secara acak (8 karakter alfanumerik) dan simpan langsung --
     * tidak pernah menerima input manual staf. Lihat CONTEXT.md "PPP Password Credential".
     */
    public function regeneratePppPassword(): void
    {
        $layanan = LayananPelanggan::findOrFail($this->layananId);
        $this->authorize('update', $layanan);

        $newPassword = LayananPelanggan::generatePppPassword();

        $layanan->update(['ppp_password_terenkripsi' => $newPassword]);

        $this->generatedPppPassword = $newPassword;

        Flux::toast(variant: 'success', text: 'PPP Password berhasil digenerate.');
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
                'label' => fn (PaketLayanan $pk) => $pk->nama_paket.' — '.$pk->formattedHarga(),
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
        $ipPubliks = $this->router_id
            ? IpPublik::where('router_id', $this->router_id)
                ->where(fn ($q) => $q->whereNull('layanan_pelanggan_id')->orWhere('layanan_pelanggan_id', $this->layananId))
                ->orderBy('alamat_ip')
                ->get()
            : collect();
        $jenisKoneksi = JenisKoneksi::cases();
        $statuses = StatusLayanan::cases();

        return view('livewire.layanan-pelanggan.edit', compact(
            'routers',
            'ipPools',
            'ipPubliks',
            'jenisKoneksi',
            'statuses',
        ));
    }
}
