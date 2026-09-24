<?php

namespace App\Livewire\BarangKeluar;

use App\Livewire\Concerns\HasSearchableOptions;
use App\Models\Barang;
use App\Models\BarangKeluar;
use App\Services\Inventaris\BarangService;
use Exception;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Catat Barang Keluar')]
class Create extends Component
{
    use HasSearchableOptions;

    public ?int $barang_id = null;

    public string $tanggal = '';

    public ?int $jumlah_keluar = null;

    public string $keterangan = '';

    public string $teknisi = '';

    public function mount(): void
    {
        $this->authorize('create', BarangKeluar::class);
        $this->tanggal = Carbon::today()->toDateString();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'barang_id' => ['required', 'integer', 'exists:barang,id'],
            'tanggal' => ['required', 'date'],
            'jumlah_keluar' => ['required', 'integer', 'min:1'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'teknisi' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'barang_id.required' => 'Barang wajib dipilih.',
            'jumlah_keluar.required' => 'Jumlah barang keluar wajib diisi.',
            'jumlah_keluar.min' => 'Jumlah barang keluar harus lebih dari 0.',
        ];
    }

    public function save(BarangService $barangService): void
    {
        $this->authorize('create', BarangKeluar::class);
        $this->validate();

        $barang = Barang::findOrFail($this->barang_id);

        try {
            $barangKeluar = $barangService->catatBarangKeluar(
                barang: $barang,
                jumlah: (int) $this->jumlah_keluar,
                tanggal: Carbon::parse($this->tanggal),
                keterangan: $this->keterangan ?: null,
                teknisi: $this->teknisi ?: null,
                dicatatOleh: Auth::id(),
            );

            Flux::toast(variant: 'success', text: "Barang keluar untuk {$barang->nama_barang} sejumlah {$barangKeluar->jumlah_keluar} berhasil dicatat.");
            $this->redirectRoute('barang-keluar.index', navigate: true);
        } catch (Exception $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());
        }
    }

    /**
     * @return array<string, array{model: class-string, query: \Closure, label: \Closure, cap?: int}>
     */
    protected function searchableFields(): array
    {
        return [
            'barang_id' => [
                'model' => Barang::class,
                'query' => fn () => Barang::query()->where('is_active', true),
                'label' => fn (Barang $b) => "{$b->kode_barang} — {$b->nama_barang} (stok: {$b->stok})",
                'cap' => 20,
            ],
        ];
    }

    public function render(): View
    {
        return view('livewire.barang-keluar.create');
    }
}
