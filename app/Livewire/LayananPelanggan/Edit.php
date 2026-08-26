<?php

namespace App\Livewire\LayananPelanggan;

use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
use App\Models\IpPool;
use App\Models\LayananPelanggan;
use App\Models\PaketLayanan;
use App\Models\Router;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Data Registrasi Billing')]
class Edit extends Component
{
    #[Locked]
    public int $layananId;

    #[Locked]
    public string $pelangganNoReg = '';

    public ?int $paket_layanan_id = null;

    public ?int $router_id = null;

    public ?int $ip_pool_id = null;

    public ?string $ip_static = null;

    public string $nama_site = '';

    public string $alamat_pemasangan = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $ppp_username = '';

    public string $ppp_password = '';

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
        $this->nama_site = $layananPelanggan->nama_site ?? '';
        $this->alamat_pemasangan = $layananPelanggan->alamat_pemasangan ?? '';
        $this->latitude = $layananPelanggan->latitude;
        $this->longitude = $layananPelanggan->longitude;
        $this->ppp_username = $layananPelanggan->ppp_username;
        $this->ppp_password = ''; // Kosongkan untuk keamanan
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
                "unique:layanan_pelanggan,ppp_username,{$this->layananId}",
            ],
            'ppp_password' => ['nullable', 'string', 'min:4', 'max:64'],
            'status' => ['required', 'string'],
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
            'ip_pool_id.exists' => 'IP Pool yang dipilih tidak valid.',
            'ip_static.required' => 'Alamat IP Statis wajib diisi untuk koneksi IP Static.',
            'ip_static.ipv4' => 'Format Alamat IP Statis tidak valid (contoh: 192.168.1.50).',
            'ppp_username.required' => 'Username PPP wajib diisi.',
            'ppp_username.regex' => 'Format username PPP tidak valid. Harus berupa No.Reg pelanggan diikuti underscore dan 5 digit angka (contoh: BF2308202601_00001).',
            'ppp_username.max' => 'Username PPP maksimal 64 karakter.',
            'ppp_password.min' => 'Password PPP minimal 4 karakter.',
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
            'status' => $this->status,
            'tanggal_mulai' => $this->tanggal_mulai,
            'tanggal_expired' => $this->tanggal_expired ?: null,
        ];

        // Hanya update password jika diisi
        if (! empty($this->ppp_password)) {
            $data['ppp_password_terenkripsi'] = $this->ppp_password;
        }

        $layanan->update($data);

        Flux::toast(variant: 'success', text: 'Data Registrasi Billing berhasil diperbarui.');
        $this->redirectRoute('layanan-pelanggan.index', navigate: true);
    }

    public function render(): View
    {
        $pakets = PaketLayanan::aktif()->with('profilBandwidth')->orderBy('nama_paket')->get();
        $routers = Router::online()->get();
        $ipPools = $this->router_id
            ? IpPool::where('router_id', $this->router_id)->orderBy('nama_pool')->get()
            : collect();
        $jenisKoneksi = JenisKoneksi::cases();
        $statuses = StatusLayanan::cases();

        return view('livewire.layanan-pelanggan.edit', compact(
            'pakets',
            'routers',
            'ipPools',
            'jenisKoneksi',
            'statuses',
        ));
    }
}
