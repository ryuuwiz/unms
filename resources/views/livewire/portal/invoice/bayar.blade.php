<div class="max-w-2xl mx-auto space-y-6" @if($transaksiAktif) wire:poll.5s="loadTransaksiAktif" @endif>
    <!-- Header Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('portal.invoice.show', $invoice) }}" wire:navigate class="text-xs font-semibold text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 flex items-center gap-1">
            &larr; Kembali ke Rincian Tagihan
        </a>
        <div class="font-mono text-xs font-bold text-zinc-400">
            {{ $invoice->no_invoice }}
        </div>
    </div>

    <!-- Ringkasan Singkat -->
    <flux:card class="p-4 bg-indigo-50/50 dark:bg-indigo-950/30 border-indigo-200/60 dark:border-indigo-800/40 flex justify-between items-center">
        <div>
            <div class="text-xs text-indigo-700 dark:text-indigo-300 font-semibold">Total yang Harus Dibayar:</div>
            <div class="text-xl font-bold text-indigo-950 dark:text-indigo-100 mt-0.5">
                {{ $transaksiAktif ? $transaksiAktif->formattedTotalTagihan() : $invoice->formattedJumlahSetelahPromo() }}
            </div>
        </div>
        <div class="text-right text-xs text-zinc-500">
            <div>{{ $invoice->layananPelanggan?->paketLayanan?->nama_paket }}</div>
            <div class="text-[11px] text-zinc-400">Jatuh Tempo: {{ \Carbon\Carbon::parse($invoice->tanggal_jatuh_tempo)->translatedFormat('d M Y') }}</div>
        </div>
    </flux:card>

    @if(! $transaksiAktif)
        <!-- Form Pemilihan Metode Pembayaran -->
        <flux:card class="p-6 space-y-6">
            <div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100">Pilih Metode Pembayaran</h2>
                <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Pilih channel pembayaran otomatis melalui payment gateway Xendit</p>
            </div>

            <!-- Tab Channel -->
            <div class="grid grid-cols-2 gap-3">
                <button
                    type="button"
                    wire:click="$set('channelTipe', 'va')"
                    class="p-4 rounded-xl border text-left transition-all {{ $channelTipe === 'va' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20' : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300' }}"
                >
                    <div class="flex items-center gap-2">
                        <flux:icon icon="building-library" class="size-5 text-indigo-600 dark:text-indigo-400" />
                        <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">Virtual Account</span>
                    </div>
                    <p class="text-[11px] text-zinc-500 mt-1">BCA, BNI, BRI, Mandiri, Permata</p>
                    @if($feeVa > 0)
                        <div class="text-[10px] text-zinc-400 mt-1">+ Biaya admin: Rp {{ number_format($feeVa, 0, ',', '.') }}</div>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="$set('channelTipe', 'qris')"
                    class="p-4 rounded-xl border text-left transition-all {{ $channelTipe === 'qris' ? 'border-indigo-600 bg-indigo-50/40 dark:bg-indigo-950/30 ring-2 ring-indigo-500/20' : 'border-zinc-200 dark:border-zinc-800 hover:border-zinc-300' }}"
                >
                    <div class="flex items-center gap-2">
                        <flux:icon icon="qr-code" class="size-5 text-emerald-600 dark:text-emerald-400" />
                        <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">QRIS Dinamis</span>
                    </div>
                    <p class="text-[11px] text-zinc-500 mt-1">Gopay, OVO, Dana, ShopeePay, BCA/Livin QR</p>
                    @if($feeQris > 0)
                        <div class="text-[10px] text-zinc-400 mt-1">+ Biaya admin: Rp {{ number_format($feeQris, 0, ',', '.') }}</div>
                    @endif
                </button>
            </div>

            @if($channelTipe === 'va')
                <!-- Pilihan Bank VA -->
                <div class="space-y-3 pt-2">
                    <label class="text-xs font-bold text-zinc-700 dark:text-zinc-300 uppercase tracking-wider">
                        Pilih Bank Penerbit Virtual Account:
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                        @php
                            $banks = [
                                ['code' => 'BCA', 'name' => 'BCA Virtual Account'],
                                ['code' => 'BNI', 'name' => 'BNI Virtual Account'],
                                ['code' => 'BRI', 'name' => 'BRI Virtual Account (BRIVA)'],
                                ['code' => 'MANDIRI', 'name' => 'Mandiri Virtual Account'],
                                ['code' => 'PERMATA', 'name' => 'Permata Virtual Account'],
                            ];
                        @endphp

                        @foreach($banks as $b)
                            <label class="flex items-center justify-between p-3.5 rounded-xl border cursor-pointer transition-all {{ $bankCode === $b['code'] ? 'border-indigo-600 bg-indigo-50/30 dark:bg-indigo-950/20 text-indigo-950 dark:text-indigo-100 font-semibold' : 'border-zinc-200 dark:border-zinc-800 text-zinc-700 dark:text-zinc-300 hover:bg-zinc-50 dark:hover:bg-zinc-800/50' }}">
                                <div class="flex items-center gap-3">
                                    <input type="radio" wire:model.live="bankCode" value="{{ $b['code'] }}" class="text-indigo-600 focus:ring-indigo-500" />
                                    <span class="text-xs">{{ $b['name'] }}</span>
                                </div>
                                <span class="font-mono text-xs font-bold px-1.5 py-0.5 rounded bg-zinc-100 dark:bg-zinc-800">{{ $b['code'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800">
                <flux:button wire:click="generatePembayaran" variant="primary" class="w-full justify-center bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-2.5 shadow-lg shadow-indigo-600/20">
                    <flux:icon icon="arrow-right" class="size-4 mr-1.5" />
                    Lanjutkan Pembayaran
                </flux:button>
            </div>
        </flux:card>
    @else
        <!-- Tampilan Kode Pembayaran Aktif -->
        <flux:card class="p-6 space-y-6 text-center">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-50 dark:bg-amber-950/50 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 text-xs font-semibold">
                    <span class="size-2 rounded-full bg-amber-500 animate-pulse"></span>
                    Menunggu Pembayaran
                </div>
                <h2 class="text-lg font-bold text-zinc-900 dark:text-zinc-100 pt-2">
                    {{ $transaksiAktif->channel === \App\Enums\GatewayChannel::Qris ? 'Scan Kode QRIS' : 'Transfer ke Nomor Virtual Account' }}
                </h2>
                <p class="text-xs text-zinc-500">
                    Batas waktu pembayaran sampai: <strong class="text-zinc-800 dark:text-zinc-200">{{ \Carbon\Carbon::parse($transaksiAktif->expired_at)->translatedFormat('d F Y, H:i') }} WIB</strong>
                </p>
            </div>

            @if($transaksiAktif->channel === \App\Enums\GatewayChannel::VirtualAccount)
                <!-- Detail Nomor VA -->
                <div class="bg-zinc-50 dark:bg-zinc-800/80 p-5 rounded-2xl border border-zinc-200 dark:border-zinc-700 max-w-md mx-auto space-y-3">
                    <div class="text-xs text-zinc-500 font-medium">
                        Bank {{ strtoupper($transaksiAktif->channel_detail ?? 'VA') }}
                    </div>
                    <div class="font-mono text-2xl sm:text-3xl font-extrabold text-indigo-600 dark:text-indigo-400 tracking-wider select-all" id="va-number">
                        {{ $transaksiAktif->nomor_pembayaran }}
                    </div>

                    <div class="pt-2">
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText('{{ $transaksiAktif->nomor_pembayaran }}'); alert('Nomor Virtual Account disalin ke clipboard!');"
                            class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-white dark:bg-zinc-700 text-xs font-semibold border border-zinc-300 dark:border-zinc-600 shadow-xs hover:bg-zinc-50 dark:hover:bg-zinc-600 transition-colors"
                        >
                            <flux:icon icon="clipboard-document" class="size-4 text-zinc-500" />
                            Salin Nomor VA
                        </button>
                    </div>
                </div>
            @elseif($transaksiAktif->channel === \App\Enums\GatewayChannel::Qris)
                <!-- QR Code Display -->
                <div class="bg-white p-6 rounded-2xl border border-zinc-200 dark:border-zinc-700 max-w-xs mx-auto shadow-md">
                    @if($transaksiAktif->qr_string)
                        <img
                            src="https://api.qrserver.com/v1/create-qr-code/?size=240x240&data={{ urlencode($transaksiAktif->qr_string) }}"
                            alt="QRIS Code"
                            class="size-60 mx-auto rounded-lg"
                        />
                    @else
                        <div class="size-60 mx-auto bg-zinc-100 flex items-center justify-center text-xs text-zinc-400">
                            QR Code tidak tersedia
                        </div>
                    @endif
                    <div class="mt-3 text-[11px] text-zinc-500 font-semibold">
                        NMID: QRIS Standar Bank Indonesia
                    </div>
                </div>
            @endif

            <!-- Rincian Jumlah Total -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl text-xs space-y-1.5 text-zinc-600 dark:text-zinc-400 max-w-md mx-auto">
                <div class="flex justify-between">
                    <span>Nominal Invoice:</span>
                    <span>{{ $invoice->formattedJumlahSetelahPromo() }}</span>
                </div>
                @if($transaksiAktif->fee_gateway > 0)
                    <div class="flex justify-between">
                        <span>Biaya Admin Gateway:</span>
                        <span>{{ $transaksiAktif->formattedFeeGateway() }}</span>
                    </div>
                @endif
                <div class="flex justify-between font-bold text-sm text-zinc-900 dark:text-zinc-100 pt-1.5 border-t border-zinc-200 dark:border-zinc-700">
                    <span>Total Pembayaran:</span>
                    <span class="text-indigo-600 dark:text-indigo-400">{{ $transaksiAktif->formattedTotalTagihan() }}</span>
                </div>
            </div>

            <!-- Tombol Aksi -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 pt-2">
                <flux:button wire:click="cekStatus" variant="primary" class="w-full sm:w-auto bg-emerald-600 hover:bg-emerald-500 text-white font-bold">
                    <flux:icon icon="arrow-path" class="size-4 mr-1.5" />
                    Saya Sudah Bayar (Cek Status)
                </flux:button>

                <flux:button wire:click="gantiMetode" variant="ghost" class="w-full sm:w-auto text-xs text-zinc-500">
                    Ganti Metode Pembayaran
                </flux:button>
            </div>
        </flux:card>
    @endif
</div>
