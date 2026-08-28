<?php

namespace App\Livewire\Billing\AturanPengingat;

use App\Enums\Wa\StatusAntrianWa;
use App\Enums\Wa\TipePengingatTagihan;
use App\Models\AntrianWaBlast;
use App\Models\AturanPengingatTagihan;
use App\Models\Invoice;
use App\Models\WaTemplate;
use Flux\Flux;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Aturan Pengingat Tagihan')]
class Index extends Component
{
    // State Modal Tambah/Edit
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $nama_aturan = '';

    public string $tipe_pengingat = 'sebelum_jatuh_tempo';

    public int $hari_offset = 3;

    public string $jam_eksekusi = '08:30';

    public ?int $template_id = null;

    public bool $kirim_ulang_berkala = false;

    public ?int $interval_hari = null;

    public bool $is_aktif = true;

    // State Eksekusi Manual
    public bool $isExecuting = false;

    public function mount(): void
    {
        $this->authorize('viewAny', Invoice::class);
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->template_id = WaTemplate::query()->active()->first()?->id;
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $aturan = AturanPengingatTagihan::findOrFail($id);

        $this->editingId = $aturan->id;
        $this->nama_aturan = $aturan->nama_aturan;
        $this->tipe_pengingat = $aturan->tipe_pengingat->value;
        $this->hari_offset = $aturan->hari_offset;
        $this->jam_eksekusi = Carbon::parse($aturan->jam_eksekusi)->format('H:i');
        $this->template_id = $aturan->template_id;
        $this->kirim_ulang_berkala = (bool) $aturan->kirim_ulang_berkala;
        $this->interval_hari = $aturan->interval_hari;
        $this->is_aktif = (bool) $aturan->is_aktif;

        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->validate([
            'nama_aturan' => ['required', 'string', 'max:255'],
            'tipe_pengingat' => ['required', Rule::enum(TipePengingatTagihan::class)],
            'hari_offset' => ['required', 'integer', 'min:0', 'max:90'],
            'jam_eksekusi' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
            'template_id' => ['required', 'integer', 'exists:wa_template,id'],
            'kirim_ulang_berkala' => ['required', 'boolean'],
            'interval_hari' => ['nullable', 'required_if:kirim_ulang_berkala,true', 'integer', 'min:1', 'max:30'],
            'is_aktif' => ['required', 'boolean'],
        ], [
            'nama_aturan.required' => 'Nama aturan pengingat wajib diisi.',
            'template_id.required' => 'Template WhatsApp wajib dipilih.',
            'jam_eksekusi.regex' => 'Format jam harus JJ:MM (contoh: 08:30).',
            'interval_hari.required_if' => 'Interval hari wajib diisi jika opsi kirim ulang aktif.',
        ]);

        $data = [
            'nama_aturan' => trim($this->nama_aturan),
            'tipe_pengingat' => $this->tipe_pengingat,
            'hari_offset' => $this->hari_offset,
            'jam_eksekusi' => "{$this->jam_eksekusi}:00",
            'template_id' => $this->template_id,
            'kirim_ulang_berkala' => $this->kirim_ulang_berkala,
            'interval_hari' => $this->kirim_ulang_berkala ? $this->interval_hari : null,
            'is_aktif' => $this->is_aktif,
        ];

        if ($this->editingId) {
            $aturan = AturanPengingatTagihan::findOrFail($this->editingId);
            $aturan->update($data);
            Flux::toast(variant: 'success', text: "Aturan pengingat '{$aturan->nama_aturan}' berhasil diperbarui.");
        } else {
            $aturan = AturanPengingatTagihan::create($data);
            Flux::toast(variant: 'success', text: "Aturan pengingat '{$aturan->nama_aturan}' berhasil ditambahkan.");
        }

        $this->showModal = false;
        $this->resetForm();
    }

    public function toggleStatus(int $id): void
    {
        $aturan = AturanPengingatTagihan::findOrFail($id);
        $aturan->update(['is_aktif' => ! $aturan->is_aktif]);

        $statusText = $aturan->is_aktif ? 'diaktifkan' : 'dinonaktifkan';
        Flux::toast(variant: 'success', text: "Aturan '{$aturan->nama_aturan}' berhasil {$statusText}.");
    }

    public function hapus(int $id): void
    {
        $aturan = AturanPengingatTagihan::findOrFail($id);
        $nama = $aturan->nama_aturan;
        $aturan->delete();

        Flux::toast(variant: 'success', text: "Aturan '{$nama}' berhasil dihapus.");
    }

    public function jalankanPengingatManual(): void
    {
        try {
            $this->isExecuting = true;
            Artisan::call('invoice:kirim-pengingat', ['--force' => true]);
            $output = Artisan::output();

            Flux::toast(variant: 'success', text: 'Pengingat tagihan otomatis berhasil dieksekusi.');
        } catch (\Throwable $e) {
            Flux::toast(variant: 'danger', text: 'Gagal menjalankan pengingat: '.$e->getMessage());
        } finally {
            $this->isExecuting = false;
        }
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->nama_aturan = '';
        $this->tipe_pengingat = 'sebelum_jatuh_tempo';
        $this->hari_offset = 3;
        $this->jam_eksekusi = '08:30';
        $this->template_id = null;
        $this->kirim_ulang_berkala = false;
        $this->interval_hari = null;
        $this->is_aktif = true;
    }

    public function render(): View
    {
        $aturanList = AturanPengingatTagihan::query()
            ->with('template')
            ->orderBy('tipe_pengingat')
            ->orderBy('hari_offset')
            ->get();

        $templates = WaTemplate::query()->active()->get();

        $totalAktif = $aturanList->where('is_aktif', true)->count();
        $totalAntreanHariIni = AntrianWaBlast::whereDate('tanggal_kirim', Carbon::today())->count();
        $totalTerkirimHariIni = AntrianWaBlast::whereDate('tanggal_kirim', Carbon::today())
            ->where('status', StatusAntrianWa::Terkirim)
            ->count();

        return view('livewire.billing.aturan-pengingat.index', [
            'aturanList' => $aturanList,
            'templates' => $templates,
            'totalAktif' => $totalAktif,
            'totalAntreanHariIni' => $totalAntreanHariIni,
            'totalTerkirimHariIni' => $totalTerkirimHariIni,
            'tipeList' => TipePengingatTagihan::cases(),
        ]);
    }
}
