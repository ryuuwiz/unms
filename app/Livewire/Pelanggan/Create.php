<?php

namespace App\Livewire\Pelanggan;

use App\Enums\StatusPelanggan;
use App\Enums\TipePelanggan;
use App\Models\Pelanggan;
use App\Models\Perumahan;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Pelanggan')]
class Create extends Component
{
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

    public string $status = 'prospek';

    public function mount(): void
    {
        $this->authorize('create', Pelanggan::class);
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
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
            'nama_depan.required' => 'Nama depan pelanggan wajib diisi.',
            'no_hp.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'no_hp.regex' => 'Format nomor HP tidak valid. Gunakan awalan 08 atau +628 (contoh: 08123456789).',
            'email.email' => 'Format alamat email tidak valid.',
            'alamat_lengkap.required' => 'Alamat lengkap pemasangan wajib diisi.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', Pelanggan::class);
        $this->validate();

        $pelanggan = Pelanggan::create([
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
            'dibuat_oleh' => Auth::id(),
        ]);

        Flux::toast(variant: 'success', text: "Pelanggan {$pelanggan->namaLengkap()} ({$pelanggan->no_reg}) berhasil didaftarkan.");

        $this->redirectRoute('pelanggan.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.pelanggan.create', [
            'perumahans' => Perumahan::orderBy('nama_perumahan')->get(),
            'tipes' => TipePelanggan::cases(),
            'statuses' => StatusPelanggan::cases(),
        ]);
    }
}
