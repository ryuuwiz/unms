<?php

namespace App\Livewire\Pelanggan;

use App\Enums\StatusPelanggan;
use App\Enums\TipePelanggan;
use App\Models\Odp;
use App\Models\Pelanggan;
use App\Models\Perumahan;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Pelanggan')]
class Edit extends Component
{
    #[Locked]
    public int $pelangganId;

    public string $no_reg = '';

    public string $tipe_pelanggan = 'rumah';

    public string $nik = '';

    public string $nama_depan = '';

    public string $nama_belakang = '';

    public string $email = '';

    public string $no_hp = '';

    public string $telepon_rumah = '';

    public ?int $perumahan_id = null;

    public string $rt = '';

    public string $rw = '';

    public string $no_rumah = '';

    public string $kode_pos = '';

    public string $alamat_lengkap = '';

    public ?float $latitude = null;

    public ?float $longitude = null;

    /** @var array<int, array{id: int, nama_odp: string, jarak: float, port_kosong_count: int}> */
    public array $odpTerdekat = [];

    public string $status = 'aktif';

    public function mount(Pelanggan $pelanggan): void
    {
        $this->authorize('update', $pelanggan);

        $this->pelangganId = $pelanggan->id;
        $this->no_reg = $pelanggan->no_reg;
        $this->tipe_pelanggan = $pelanggan->tipe_pelanggan->value;
        $this->nik = $pelanggan->nik ?? '';
        $this->nama_depan = $pelanggan->nama_depan;
        $this->nama_belakang = $pelanggan->nama_belakang ?? '';
        $this->email = $pelanggan->email ?? '';
        $this->no_hp = $pelanggan->no_hp;
        $this->telepon_rumah = $pelanggan->telepon_rumah ?? '';
        $this->perumahan_id = $pelanggan->perumahan_id;
        $this->rt = $pelanggan->rt ?? '';
        $this->rw = $pelanggan->rw ?? '';
        $this->no_rumah = $pelanggan->no_rumah ?? '';
        $this->kode_pos = $pelanggan->kode_pos ?? '';
        $this->alamat_lengkap = $pelanggan->alamat_lengkap;
        $this->latitude = $pelanggan->latitude;
        $this->longitude = $pelanggan->longitude;
        $this->status = $pelanggan->status->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'no_reg' => ['required', 'string', 'max:50', Rule::unique('pelanggan', 'no_reg')->ignore($this->pelangganId)],
            'tipe_pelanggan' => ['required', 'string', 'in:rumah,bisnis'],
            'nik' => ['nullable', 'string', 'digits:16'],
            'nama_depan' => ['required', 'string', 'max:100'],
            'nama_belakang' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'no_hp' => ['required', 'string', 'regex:/^(08|\+628|628)[0-9]{8,13}$/'],
            'telepon_rumah' => ['nullable', 'string', 'max:20'],
            'perumahan_id' => ['nullable', 'integer', 'exists:perumahan,id'],
            'rt' => ['nullable', 'string', 'max:10'],
            'rw' => ['nullable', 'string', 'max:10'],
            'no_rumah' => ['nullable', 'string', 'max:20'],
            'kode_pos' => ['nullable', 'string', 'digits:5'],
            'alamat_lengkap' => ['required', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', 'string', 'in:aktif,tidak_aktif,prospek'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'no_reg.required' => 'Nomor Registrasi wajib diisi.',
            'no_reg.unique' => 'Nomor Registrasi sudah digunakan oleh pelanggan lain.',
            'nama_depan.required' => 'Nama depan pelanggan wajib diisi.',
            'no_hp.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'no_hp.regex' => 'Format nomor HP tidak valid. Gunakan awalan 08 atau +628 (contoh: 08123456789).',
            'email.email' => 'Format alamat email tidak valid.',
            'alamat_lengkap.required' => 'Alamat lengkap pemasangan wajib diisi.',
        ];
    }

    public function save(): void
    {
        $pelanggan = Pelanggan::findOrFail($this->pelangganId);
        $this->authorize('update', $pelanggan);
        $this->validate();

        $pelanggan->update([
            'no_reg' => strtoupper(trim($this->no_reg)),
            'tipe_pelanggan' => $this->tipe_pelanggan,
            'nik' => $this->nik ?: null,
            'nama_depan' => $this->nama_depan,
            'nama_belakang' => $this->nama_belakang ?: null,
            'email' => $this->email ?: null,
            'no_hp' => $this->no_hp,
            'telepon_rumah' => $this->telepon_rumah ?: null,
            'perumahan_id' => $this->perumahan_id,
            'rt' => $this->rt ?: null,
            'rw' => $this->rw ?: null,
            'no_rumah' => $this->no_rumah ?: null,
            'kode_pos' => $this->kode_pos ?: null,
            'alamat_lengkap' => $this->alamat_lengkap,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'status' => $this->status,
        ]);

        Flux::toast(variant: 'success', text: "Data pelanggan {$pelanggan->identitasLengkap()} berhasil diperbarui.");

        $this->redirectRoute('pelanggan.index', navigate: true);
    }

    public function cariOdpTerdekat(): void
    {
        $this->odpTerdekat = [];

        if (blank($this->latitude) || blank($this->longitude)) {
            Flux::toast(variant: 'warning', text: 'Silakan isi koordinat Latitude dan Longitude terlebih dahulu.');

            return;
        }

        $this->odpTerdekat = Odp::query()
            ->terdekat((float) $this->latitude, (float) $this->longitude, 300)
            ->withCount(['ports as port_kosong_count' => fn ($query) => $query->where('status', 'kosong')])
            ->take(3)
            ->get()
            ->map(fn ($odp) => [
                'id' => $odp->id,
                'nama_odp' => $odp->nama_odp,
                'jarak' => round((float) $odp->jarak),
                'port_kosong_count' => $odp->port_kosong_count,
            ])
            ->toArray();

        if (empty($this->odpTerdekat)) {
            Flux::toast(variant: 'warning', text: 'Tidak ada ODP dalam radius 300 meter.');
        }
    }

    public function render(): View
    {
        $pelanggan = Pelanggan::with('pembuat')->findOrFail($this->pelangganId);

        return view('livewire.pelanggan.edit', [
            'pelanggan' => $pelanggan,
            'perumahans' => Perumahan::orderBy('nama_perumahan')->get(),
            'tipes' => TipePelanggan::cases(),
            'statuses' => StatusPelanggan::cases(),
        ]);
    }
}
