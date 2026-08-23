<?php

namespace App\Livewire\LayananPelanggan;

use App\Enums\JenisKoneksi;
use App\Enums\StatusLayanan;
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
#[Title('Edit Layanan Pelanggan')]
class Edit extends Component
{
    #[Locked]
    public int $layananId;

    #[Locked]
    public string $pelangganNoReg = '';

    public ?int $paket_layanan_id = null;

    public ?int $router_id = null;

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
            'ppp_username' => [
                'required',
                'string',
                'max:64',
                'regex:/^'.$escapedNoReg.'_[0-9]{5}$/',
                "unique:layanan_pelanggan,ppp_username,{$this->layananId}",
            ],
            'ppp_password' => ['nullable', 'string', 'min:4', 'max:64'],
            'jenis_koneksi' => ['required', 'string', 'in:pppoe,ip_static'],
            'status' => ['required', 'string'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_expired' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ];
    }

    public function save(): void
    {
        $layanan = LayananPelanggan::findOrFail($this->layananId);
        $this->authorize('update', $layanan);
        $this->validate($this->rules(), [
            'ppp_username.regex' => 'Format username PPP tidak valid. Harus berupa No.Reg pelanggan diikuti underscore dan 5 digit angka (contoh: BF2308202601_00001).',
            'ppp_username.max' => 'Username PPP maksimal 64 karakter.',
            'ppp_password.min' => 'Password PPP minimal 4 karakter.',
        ]);

        $data = [
            'paket_layanan_id' => $this->paket_layanan_id,
            'router_id' => $this->router_id,
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

        Flux::toast(variant: 'success', text: 'Layanan pelanggan berhasil diperbarui.');
        $this->redirectRoute('layanan-pelanggan.index', navigate: true);
    }

    public function render(): View
    {
        $pakets = PaketLayanan::aktif()->with('profilBandwidth')->orderBy('nama_paket')->get();
        $routers = Router::online()->get();
        $jenisKoneksi = JenisKoneksi::cases();
        $statuses = StatusLayanan::cases();

        return view('livewire.layanan-pelanggan.edit', compact(
            'pakets',
            'routers',
            'jenisKoneksi',
            'statuses',
        ));
    }
}
