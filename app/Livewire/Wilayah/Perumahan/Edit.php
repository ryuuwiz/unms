<?php

namespace App\Livewire\Wilayah\Perumahan;

use App\Models\Kecamatan;
use App\Models\Kelurahan;
use App\Models\Kota;
use App\Models\Perumahan;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Perumahan')]
class Edit extends Component
{
    #[Locked]
    public int $perumahanId;

    public ?int $kota_id = null;

    public ?int $kecamatan_id = null;

    public ?int $kelurahan_id = null;

    public string $nama_perumahan = '';

    public string $singkatan = '';

    public ?float $lat = null;

    public ?float $lng = null;

    public string $keterangan = '';

    public function mount(Perumahan $perumahan): void
    {
        $this->authorize('update', $perumahan->kelurahan->kecamatan->kota);
        $this->perumahanId = $perumahan->id;
        $this->kelurahan_id = $perumahan->kelurahan_id;
        $this->kecamatan_id = $perumahan->kelurahan->kecamatan_id;
        $this->kota_id = $perumahan->kelurahan->kecamatan->kota_id;
        $this->nama_perumahan = $perumahan->nama_perumahan;
        $this->singkatan = $perumahan->singkatan ?? '';
        $this->lat = $perumahan->latitude ? (float) $perumahan->latitude : null;
        $this->lng = $perumahan->longitude ? (float) $perumahan->longitude : null;
        $this->keterangan = $perumahan->keterangan ?? '';
    }

    public function updatedKotaId(): void
    {
        $this->kecamatan_id = null;
        $this->kelurahan_id = null;
    }

    public function updatedKecamatanId(): void
    {
        $this->kelurahan_id = null;
    }

    public function updatedSingkatan(string $value): void
    {
        $this->singkatan = strtoupper(trim($value));
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'kota_id' => ['required', 'integer', 'exists:kota,id'],
            'kecamatan_id' => ['required', 'integer', 'exists:kecamatan,id'],
            'kelurahan_id' => ['required', 'integer', 'exists:kelurahan,id'],
            'nama_perumahan' => [
                'required',
                'string',
                'max:100',
                Rule::unique('perumahan', 'nama_perumahan')
                    ->where('kelurahan_id', $this->kelurahan_id)
                    ->ignore($this->perumahanId),
            ],
            'singkatan' => ['nullable', 'string', 'max:10', 'regex:/^[A-Z0-9_-]+$/i'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'keterangan' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'kota_id.required' => 'Kota induk wajib dipilih.',
            'kota_id.exists' => 'Kota yang dipilih tidak valid.',
            'kecamatan_id.required' => 'Kecamatan induk wajib dipilih.',
            'kecamatan_id.exists' => 'Kecamatan yang dipilih tidak valid.',
            'kelurahan_id.required' => 'Kelurahan induk wajib dipilih.',
            'kelurahan_id.exists' => 'Kelurahan yang dipilih tidak valid.',
            'nama_perumahan.required' => 'Nama perumahan/cluster wajib diisi.',
            'nama_perumahan.unique' => 'Perumahan dengan nama ini sudah terdaftar di kelurahan terpilih.',
            'singkatan.regex' => 'Singkatan hanya boleh berisi huruf kapital, angka, garis bawah, dan strip.',
            'lat.between' => 'Latitude harus bernilai antara -90 dan 90 derajat.',
            'lng.between' => 'Longitude harus bernilai antara -180 dan 180 derajat.',
        ];
    }

    public function save(): void
    {
        $perumahan = Perumahan::findOrFail($this->perumahanId);
        $this->authorize('update', $perumahan->kelurahan->kecamatan->kota);
        $this->validate();

        $perumahan->update([
            'kelurahan_id' => $this->kelurahan_id,
            'nama_perumahan' => $this->nama_perumahan,
            'singkatan' => $this->singkatan ? strtoupper($this->singkatan) : null,
            'latitude' => $this->lat,
            'longitude' => $this->lng,
            'keterangan' => $this->keterangan ?: null,
        ]);

        Flux::toast(variant: 'success', text: 'Data perumahan berhasil diperbarui.');
        $this->redirectRoute('wilayah.perumahan.index', navigate: true);
    }

    public function render(): View
    {
        $kotas = Kota::orderBy('nama_kota')->get();

        $kecamatans = $this->kota_id
            ? Kecamatan::where('kota_id', $this->kota_id)->orderBy('nama_kecamatan')->get()
            : collect();

        $kelurahans = $this->kecamatan_id
            ? Kelurahan::where('kecamatan_id', $this->kecamatan_id)->orderBy('nama_kelurahan')->get()
            : collect();

        return view('livewire.wilayah.perumahan.edit', [
            'kotas' => $kotas,
            'kecamatans' => $kecamatans,
            'kelurahans' => $kelurahans,
        ]);
    }
}
