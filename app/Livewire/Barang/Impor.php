<?php

namespace App\Livewire\Barang;

use App\Exports\Barang\TemplateImporInventarisExport;
use App\Models\User;
use App\Services\Barang\ImporInventarisService;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Impor Inventaris 3 sheet: unggah -> pratinjau -> simpan (semua-atau-tidak).
 * Lihat CONTEXT.md "Impor Inventaris".
 */
#[Layout('layouts.app')]
#[Title('Impor Inventaris')]
class Impor extends Component
{
    use WithFileUploads;

    /** @var TemporaryUploadedFile|null */
    public $berkas = null;

    /** @var array{bisa_disimpan: bool, galat_umum: list<string>, masalah: array<string, array<int, array{label: string, galat: list<string>, peringatan: list<string>}>>, jumlah: array<string, int>, disimpan: bool, teknisi_tak_dikenal: list<string>}|null */
    public ?array $hasil = null;

    /**
     * Pemetaan nama teknisi di berkas yang tidak dikenal (mis. "FERDY") ke user teknisi.
     *
     * @var array<string, int|string|null>
     */
    public array $petaTeknisi = [];

    public function mount(): void
    {
        $this->otorisasi();
    }

    public function updatedBerkas(): void
    {
        $this->hasil = null;
        $this->petaTeknisi = [];
    }

    public function unduhTemplate(): BinaryFileResponse
    {
        return Excel::download(new TemplateImporInventarisExport, 'Template-Impor-Inventaris.xlsx');
    }

    public function pratinjau(ImporInventarisService $service): void
    {
        $this->otorisasi();
        $this->validasiBerkas();

        $this->denganSalinanLokal(fn (string $path) => $this->hasil = $service->pratinjau($path, $this->peta()));
    }

    public function simpan(ImporInventarisService $service): void
    {
        $this->otorisasi();
        $this->validasiBerkas();

        $user = auth('web')->user();
        abort_unless($user !== null, 403);

        $this->denganSalinanLokal(fn (string $path) => $this->hasil = $service->simpan($path, $user, $this->peta()));

        if ($this->hasil['disimpan'] ?? false) {
            Flux::toast(variant: 'success', text: 'Impor inventaris berhasil disimpan.');
            $this->berkas = null;
        }
    }

    /**
     * @return array<string, int>
     */
    private function peta(): array
    {
        $teknisiIds = User::role('teknisi')->pluck('id')->all();

        return collect($this->petaTeknisi)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => in_array($id, $teknisiIds, true))
            ->all();
    }

    private function otorisasi(): void
    {
        $user = auth('web')->user();
        abort_unless($user !== null && $user->can('barang.buat') && $user->can('barang.masuk') && $user->can('barang.keluar'), 403);
    }

    private function validasiBerkas(): void
    {
        $this->validate(['berkas' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120']], [
            'berkas.required' => 'Pilih berkas Excel terlebih dahulu.',
            'berkas.mimes' => 'Berkas harus .xlsx atau .xls.',
            'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
        ]);
    }

    /**
     * Salin unggahan ke berkas lokal sementara: di produksi disk unggahan sementara Livewire adalah
     * S3 (lihat .ai/rules/livewire-uploads.md), jadi getRealPath() tidak tersedia.
     *
     * @param  callable(string): mixed  $proses
     */
    private function denganSalinanLokal(callable $proses): void
    {
        abort_unless($this->berkas instanceof TemporaryUploadedFile, 422);

        $path = tempnam(sys_get_temp_dir(), 'impor-inventaris-').'.'.$this->berkas->getClientOriginalExtension();

        try {
            file_put_contents($path, $this->berkas->get());
            $proses($path);
        } finally {
            @unlink($path);
        }
    }

    public function render(): View
    {
        return view('livewire.barang.impor', [
            'teknisis' => User::role('teknisi')->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
