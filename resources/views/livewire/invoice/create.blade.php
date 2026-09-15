<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Tambah Invoice</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Terbitkan tagihan bulanan otomatis atau invoice manual (mis. biaya instalasi, denda) untuk pelanggan.</p>
        </div>
        <flux:button href="{{ route('invoice.index') }}" variant="subtle" icon="arrow-left" wire:navigate>
            Kembali ke Daftar
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-5">
            <div>
                <flux:radio.group wire:model.live="jenisInvoice" label="Jenis Invoice" variant="segmented">
                    <flux:radio value="tagihan_bulanan">Tagihan Bulanan (Otomatis dari Paket)</flux:radio>
                    <flux:radio value="manual">Invoice Manual / Lainnya</flux:radio>
                </flux:radio.group>
            </div>

            <h3 class="text-base font-semibold text-zinc-900 dark:text-white border-b border-zinc-200 dark:border-zinc-700 pb-3">
                1. Pemilihan Pelanggan & Layanan
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    @if($pelangganLocked)
                        <flux:input
                            label="Nama Pelanggan *"
                            :value="$pelanggans->firstWhere('id', $pelanggan_id)?->labelSelector()"
                            readonly
                        />
                    @else
                        <flux:select wire:model.live="pelanggan_id" label="Pilih Pelanggan *" placeholder="-- Pilih Pelanggan --">
                            <flux:select.option value="">-- Pilih Pelanggan --</flux:select.option>
                            @foreach($pelanggans as $p)
                                <flux:select.option value="{{ $p->id }}">{{ $p->labelSelector() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    <flux:error name="pelanggan_id" />
                </div>

                <div>
                    <flux:select wire:model.live="layanan_pelanggan_id" label="Layanan Terkait *" placeholder="-- Pilih Layanan Pelanggan --" :disabled="!$pelanggan_id">
                        <flux:select.option value="">-- Pilih Layanan Pelanggan --</flux:select.option>
                        @foreach($layanans as $lay)
                            <flux:select.option value="{{ $lay->id }}">
                                {{ $lay->site_id }} - {{ $lay->paketLayanan?->nama_paket }} (PPP: {{ $lay->ppp_username }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="layanan_pelanggan_id" />
                </div>
            </div>

            @if($jenisInvoice === 'manual')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2">
                    <div>
                        <flux:textarea wire:model="keterangan" label="Detail / Keterangan Invoice *" placeholder="Contoh: Biaya instalasi pemasangan baru, denda keterlambatan, dll." rows="2" />
                        <flux:error name="keterangan" />
                    </div>
                    <div>
                        <flux:input type="number" wire:model.live="jumlahManual" label="Total Jumlah (Rp) *" placeholder="250000" description="Masukkan hanya angka, tanpa titik/koma." />
                        <flux:error name="jumlahManual" />
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5 pt-2">
                @if($jenisInvoice === 'tagihan_bulanan')
                    <div>
                        <flux:input type="text" wire:model="periode_tagihan" label="Periode Tagihan (YYYY-MM) *" placeholder="2026-08" />
                        <flux:error name="periode_tagihan" />
                    </div>
                @endif

                <div>
                    <flux:input type="date" wire:model="tanggal_jatuh_tempo" label="Tanggal Jatuh Tempo *" />
                    <flux:error name="tanggal_jatuh_tempo" />
                </div>

                <div>
                    <flux:select wire:model.live="promo_id" label="Pilih Promo (Opsional)" placeholder="-- Tanpa Promo --">
                        <flux:select.option value="">-- Tanpa Promo --</flux:select.option>
                        @foreach($promos as $promo)
                            <flux:select.option value="{{ $promo->id }}">
                                {{ $promo->kode_promo }} - {{ $promo->nama_promo }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:input wire:model.live="kodePromo" label="Kode Promo (Opsional)" placeholder="Ketik kode promo global/musiman" :disabled="(bool) $promo_id" />
                    <flux:error name="kodePromo" />
                </div>
            </div>

            @if($existingInvoiceWarning)
                <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-start gap-3">
                    <flux:icon name="exclamation-triangle" class="w-5 h-5 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
                    <div class="text-sm text-amber-800 dark:text-amber-200">
                        <span class="font-semibold">Peringatan Tagihan Ganda:</span> {{ $existingInvoiceWarning }}
                    </div>
                </div>
            @endif
        </div>

        <!-- Ringkasan Perhitungan Biaya -->
        @if(($jenisInvoice === 'manual' && $jumlahManual) || ($jenisInvoice === 'tagihan_bulanan' && $layanan_pelanggan_id))
            <div class="bg-blue-50 dark:bg-blue-950/40 p-6 rounded-xl border border-blue-200 dark:border-blue-800 space-y-3">
                <h4 class="font-semibold text-blue-900 dark:text-blue-200 text-sm uppercase tracking-wide">
                    Ringkasan Tagihan {{ $jenisInvoice === 'tagihan_bulanan' ? "(Periode: {$periode_tagihan})" : '' }}
                </h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-zinc-700 dark:text-zinc-300">
                        <span>{{ $jenisInvoice === 'tagihan_bulanan' ? 'Tarif Paket Layanan:' : 'Jumlah Invoice:' }}</span>
                        <span class="font-semibold">Rp {{ number_format($hargaAsli, 0, ',', '.') }}</span>
                    </div>
                    @if($totalDiskon > 0)
                        <div class="flex justify-between text-emerald-600 dark:text-emerald-400">
                            <span>Potongan Diskon Promo:</span>
                            <span class="font-semibold">-Rp {{ number_format($totalDiskon, 0, ',', '.') }}</span>
                        </div>
                    @endif
                    <div class="flex justify-between text-base font-bold text-zinc-900 dark:text-white border-t border-blue-200 dark:border-blue-800 pt-2">
                        <span>Total yang Harus Dibayar:</span>
                        <span class="text-blue-600 dark:text-blue-400">Rp {{ number_format($totalTagihan, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        @endif

        <div class="flex justify-end gap-3">
            <flux:button href="{{ route('invoice.index') }}" variant="subtle" wire:navigate>
                Batal
            </flux:button>
            <flux:button
                type="submit"
                variant="primary"
                icon="document-check"
                :disabled="$jenisInvoice === 'tagihan_bulanan' && (!$layanan_pelanggan_id || (bool) $existingInvoiceWarning)"
            >
                Terbitkan Invoice
            </flux:button>
        </div>
    </form>
</div>
