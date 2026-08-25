<?php

namespace App\Livewire\Pelanggan;

use App\Enums\StatusPelanggan;
use App\Enums\TipePelanggan;
use App\Models\Odp;
use App\Models\Pelanggan;
use App\Models\Perumahan;
use App\Services\CustomerDocumentService;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Tambah Pelanggan')]
class Create extends Component
{
    use WithFileUploads;

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

    /** @var mixed */
    public $foto_ktp = null;

    /** @var mixed */
    public $dokumen_mou = null;

    public string $jenis_dokumen = 'MOU / Kontrak';

    public string $nomor_dokumen = '';

    public string $keterangan_dokumen = '';

    /** @var array<int, array{id: int, nama_odp: string, jarak: float, port_kosong_count: int}> */
    public array $odpTerdekat = [];

    public string $status = 'belum_terpasang';

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
            'no_reg' => ['nullable', 'string', 'max:50', 'unique:pelanggan,no_reg'],
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
            'status' => ['required', 'string', Rule::enum(StatusPelanggan::class)],
            'foto_ktp' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
            'dokumen_mou' => ['nullable', 'file', 'mimes:pdf,jpeg,png,jpg,webp', 'max:10240'],
            'jenis_dokumen' => ['nullable', 'string', 'max:100'],
            'nomor_dokumen' => ['nullable', 'string', 'max:100'],
            'keterangan_dokumen' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'no_reg.unique' => 'Nomor Registrasi sudah digunakan oleh pelanggan lain.',
            'nama_depan.required' => 'Nama depan pelanggan wajib diisi.',
            'no_hp.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'no_hp.regex' => 'Format nomor HP tidak valid. Gunakan awalan 08 atau +628 (contoh: 08123456789).',
            'email.email' => 'Format alamat email tidak valid.',
            'alamat_lengkap.required' => 'Alamat lengkap pemasangan wajib diisi.',
            'foto_ktp.image' => 'Berkas KTP harus berupa gambar (JPG, PNG, atau WEBP).',
            'foto_ktp.max' => 'Ukuran berkas KTP maksimal 5MB.',
            'dokumen_mou.mimes' => 'Berkas MOU / dokumen harus berformat PDF, JPG, PNG, atau WEBP.',
            'dokumen_mou.max' => 'Ukuran berkas MOU / dokumen maksimal 10MB.',
        ];
    }

    public function save(CustomerDocumentService $documentService): void
    {
        $this->authorize('create', Pelanggan::class);
        $this->validate();

        $data = [
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
        ];

        if (! empty(trim($this->no_reg))) {
            $data['no_reg'] = strtoupper(trim($this->no_reg));
        }

        $pelanggan = Pelanggan::create($data);

        // Simpan Berkas KTP Terenkripsi
        if ($this->foto_ktp) {
            $documentService->storeEncryptedMedia(
                $pelanggan,
                $this->foto_ktp,
                'ktp'
            );
        }

        // Simpan Berkas MOU Terenkripsi (jika diunggah)
        if ($this->dokumen_mou) {
            $documentService->storeEncryptedMedia(
                $pelanggan,
                $this->dokumen_mou,
                'dokumen',
                [
                    'jenis_dokumen' => $this->jenis_dokumen ?: 'MOU / Kontrak',
                    'nomor_dokumen' => $this->nomor_dokumen ?: null,
                    'keterangan' => $this->keterangan_dokumen ?: null,
                    'uploaded_by' => Auth::user()?->name,
                ]
            );
        }

        Flux::toast(variant: 'success', text: "Pelanggan {$pelanggan->identitasLengkap()} berhasil didaftarkan.");

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
        return view('livewire.pelanggan.create', [
            'perumahans' => Perumahan::orderBy('nama_perumahan')->get(),
            'tipes' => TipePelanggan::cases(),
            'statuses' => StatusPelanggan::cases(),
        ]);
    }
}
