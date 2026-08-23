<?php

namespace App\Livewire\Invoice;

use App\Enums\StatusInvoice;
use App\Models\Invoice;
use App\Models\LayananPelanggan;
use App\Models\Pelanggan;
use App\Models\Promo;
use App\Services\Billing\BillingService;
use Flux\Flux;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Terbitkan Invoice')]
class Create extends Component
{
    public ?int $pelanggan_id = null;

    public ?int $layanan_pelanggan_id = null;

    public ?int $promo_id = null;

    public string $periode_tagihan = '';

    public string $tanggal_jatuh_tempo = '';

    public ?string $existingInvoiceWarning = null;

    public float $hargaAsli = 0.0;

    public float $totalDiskon = 0.0;

    public float $totalTagihan = 0.0;

    public function mount(): void
    {
        $this->authorize('create', Invoice::class);
        $this->tanggal_jatuh_tempo = Carbon::today()->addDays(7)->toDateString();
        $this->periode_tagihan = Carbon::today()->format('Y-m');
    }

    public function updatedPelangganId(): void
    {
        $this->layanan_pelanggan_id = null;
        $this->promo_id = null;
        $this->existingInvoiceWarning = null;
        $this->recalculate();
    }

    public function updatedLayananPelangganId(): void
    {
        $this->recalculate();
    }

    public function updatedPromoId(): void
    {
        $this->recalculate();
    }

    public function recalculate(): void
    {
        $this->existingInvoiceWarning = null;

        if (! $this->layanan_pelanggan_id) {
            $this->hargaAsli = 0.0;
            $this->totalDiskon = 0.0;
            $this->totalTagihan = 0.0;
            $this->periode_tagihan = Carbon::today()->format('Y-m');

            return;
        }

        $layanan = LayananPelanggan::with('paketLayanan')->find($this->layanan_pelanggan_id);
        if (! $layanan || ! $layanan->paketLayanan) {
            return;
        }

        $this->periode_tagihan = $layanan->getNextPeriodeTagihan();
        $this->hargaAsli = (float) $layanan->paketLayanan->harga;

        $this->totalDiskon = 0.0;
        if ($this->promo_id) {
            $promo = Promo::find($this->promo_id);
            if ($promo) {
                $this->totalDiskon = $promo->hitungDiskon($this->hargaAsli);
            }
        }

        $this->totalTagihan = max(0.0, $this->hargaAsli - $this->totalDiskon);

        // Cek apakah sudah ada invoice berjalan untuk periode ini
        $existing = Invoice::where('layanan_pelanggan_id', $layanan->id)
            ->where('periode_tagihan', $this->periode_tagihan)
            ->where('status', '!=', StatusInvoice::Dibatalkan)
            ->first();

        if ($existing) {
            $this->existingInvoiceWarning = "Layanan ini sudah memiliki invoice {$existing->no_invoice} untuk periode {$this->periode_tagihan} (Status: {$existing->status->label()}).";
        }
    }

    public function save(BillingService $billingService): void
    {
        $this->authorize('create', Invoice::class);

        $this->validate([
            'pelanggan_id' => ['required', 'integer', 'exists:pelanggan,id'],
            'layanan_pelanggan_id' => ['required', 'integer', 'exists:layanan_pelanggan,id'],
            'promo_id' => ['nullable', 'integer', 'exists:promo,id'],
            'periode_tagihan' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'tanggal_jatuh_tempo' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'pelanggan_id.required' => 'Pelanggan wajib dipilih.',
            'layanan_pelanggan_id.required' => 'Layanan internet pelanggan wajib dipilih.',
            'periode_tagihan.required' => 'Periode tagihan wajib terisi.',
            'periode_tagihan.regex' => 'Format periode tagihan harus YYYY-MM.',
            'tanggal_jatuh_tempo.required' => 'Tanggal jatuh tempo wajib diisi.',
            'tanggal_jatuh_tempo.after_or_equal' => 'Tanggal jatuh tempo tidak boleh kurang dari hari ini.',
        ]);

        $layanan = LayananPelanggan::findOrFail($this->layanan_pelanggan_id);

        // Strict guard: tolak jika sudah ada invoice untuk periode ini
        $existingInvoice = Invoice::where('layanan_pelanggan_id', $layanan->id)
            ->where('periode_tagihan', $this->periode_tagihan)
            ->where('status', '!=', StatusInvoice::Dibatalkan)
            ->first();

        if ($existingInvoice) {
            $this->addError('layanan_pelanggan_id', "Layanan ini sudah memiliki invoice ({$existingInvoice->no_invoice}) pada periode tagihan {$this->periode_tagihan}.");

            return;
        }

        $promo = $this->promo_id ? Promo::find($this->promo_id) : null;
        $jatuhTempo = Carbon::parse($this->tanggal_jatuh_tempo);

        $invoice = $billingService->generateInvoice(
            layanan: $layanan,
            dibuatOleh: auth()->id(),
            promo: $promo,
            tanggalJatuhTempo: $jatuhTempo,
            periodeTagihan: $this->periode_tagihan
        );

        Flux::toast(variant: 'success', text: "Invoice {$invoice->no_invoice} berhasil diterbitkan.");

        $this->redirectRoute('invoice.show', $invoice, navigate: true);
    }

    public function render(): View
    {
        /** @var Collection<int, Pelanggan> $pelanggans */
        $pelanggans = Pelanggan::query()->orderBy('nama_depan')->get();

        /** @var Collection<int, LayananPelanggan> $layanans */
        $layanans = $this->pelanggan_id
            ? LayananPelanggan::query()
                ->with('paketLayanan')
                ->where('pelanggan_id', $this->pelanggan_id)
                ->get()
            : collect();

        /** @var Collection<int, Promo> $promos */
        $promos = Promo::query()->aktif()->get();

        return view('livewire.invoice.create', [
            'pelanggans' => $pelanggans,
            'layanans' => $layanans,
            'promos' => $promos,
        ]);
    }
}
