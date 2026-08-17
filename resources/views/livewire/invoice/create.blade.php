<div class="max-w-4xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Terbitkan Tagihan (Invoice) Manual</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Pilih pelanggan dan layanan aktif untuk membuat tagihan baru.</p>
        </div>
        <flux:button href="{{ route('invoice.index') }}" variant="subtle" icon="arrow-left" wire:navigate>
            Kembali ke Daftar
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-5">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-white border-b border-zinc-200 dark:border-zinc-700 pb-3">
                1. Pemilihan Pelanggan & Layanan
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <flux:select wire:model.live="pelanggan_id" label="Pilih Pelanggan *" placeholder="-- Pilih Pelanggan --">
                        <flux:select.option value="">-- Pilih Pelanggan --</flux:select.option>
                        @foreach($pelanggans as $p)
                            <flux:select.option value="{{ $p->id }}">{{ $p->no_reg }} - {{ $p->nama_lengkap }} ({{ $p->no_hp }})</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="pelanggan_id" />
                </div>

                <div>
                    <flux:select wire:model.live="layanan_pelanggan_id" label="Pilih Layanan Internet *" placeholder="-- Pilih Layanan Pelanggan --" :disabled="!$pelanggan_id">
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5 pt-2">
                <div>
                    <flux:select wire:model.live="promo_id" label="Gunakan Kupon Promo (Opsional)" placeholder="-- Tanpa Promo --" :disabled="!$layanan_pelanggan_id">
                        <flux:select.option value="">-- Tanpa Promo --</flux:select.option>
                        @foreach($promos as $promo)
                            <flux:select.option value="{{ $promo->id }}">
                                {{ $promo->kode_promo }} - {{ $promo->nama_promo }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <div>
                    <flux:input type="date" wire:model="tanggal_jatuh_tempo" label="Tanggal Jatuh Tempo *" />
                    <flux:error name="tanggal_jatuh_tempo" />
                </div>
            </div>
        </div>

        <!-- Ringkasan Perhitungan Biaya -->
        @if($layanan_pelanggan_id)
            <div class="bg-blue-50 dark:bg-blue-950/40 p-6 rounded-xl border border-blue-200 dark:border-blue-800 space-y-3">
                <h4 class="font-semibold text-blue-900 dark:text-blue-200 text-sm uppercase tracking-wide">
                    Ringkasan Tagihan
                </h4>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-zinc-700 dark:text-zinc-300">
                        <span>Tarif Paket Layanan:</span>
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
            <flux:button type="submit" variant="primary" icon="document-check" :disabled="!$layanan_pelanggan_id">
                Terbitkan Invoice
            </flux:button>
        </div>
    </form>
</div>
