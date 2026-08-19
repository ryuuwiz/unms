<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="{{ route('pembayaran.transaksi-gateway.index') }}" wire:navigate class="text-xs font-semibold text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 flex items-center gap-1">
                &larr; Kembali ke Daftar Transaksi Gateway
            </a>
            <flux:heading size="xl" class="mt-2">Detail Transaksi Gateway</flux:heading>
            <flux:subheading font-mono class="text-xs">{{ $transaksi->external_id }}</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            @if(($transaksi->status === \App\Enums\StatusTransaksiGateway::Pending) && (\App\Models\PengaturanGateway::getXenditSetting()->sandbox_mode || app()->environment('local', 'testing')))
                <flux:button wire:click="simulasikanPembayaran" size="sm" variant="primary" icon="bolt">
                    Simulasikan Pembayaran (Sandbox)
                </flux:button>
            @endif

            <flux:button wire:click="rekonsiliasiStatus" size="sm" variant="ghost" icon="arrow-path">
                Rekonsiliasi Status ke Xendit
            </flux:button>
        </div>
    </div>

    @if($reconciliationResult)
        <flux:card class="p-4 bg-indigo-50/60 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800 text-xs">
            <div class="font-bold text-indigo-900 dark:text-indigo-200 mb-1">Hasil Rekonsiliasi API Xendit:</div>
            <pre class="overflow-x-auto text-[11px] p-2 bg-white/80 dark:bg-zinc-900/80 rounded-lg font-mono">{{ json_encode($reconciliationResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </flux:card>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Informasi Transaksi -->
        <flux:card class="p-6 space-y-4">
            <flux:heading size="md">Informasi Pembayaran Gateway</flux:heading>

            <div class="space-y-2.5 text-xs">
                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Status:</span>
                    <flux:badge size="sm" variant="pill" :color="$transaksi->status->color()">
                        {{ $transaksi->status->label() }}
                    </flux:badge>
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Gateway:</span>
                    <span class="font-semibold uppercase">{{ $transaksi->gateway }}</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Kanal Pembayaran:</span>
                    <span class="font-semibold">{{ $transaksi->channel->label() }} ({{ strtoupper($transaksi->channel_detail ?? '-') }})</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Nomor Pembayaran (VA/QR):</span>
                    <span class="font-mono font-bold">{{ $transaksi->nomor_pembayaran ?? '-' }}</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Xendit Reference ID:</span>
                    <span class="font-mono">{{ $transaksi->xendit_reference_id ?? '-' }}</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Total Tagihan:</span>
                    <span class="font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $transaksi->formattedTotalTagihan() }}</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Biaya Admin Gateway:</span>
                    <span>{{ $transaksi->formattedFeeGateway() }}</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Waktu Dibuat:</span>
                    <span>{{ $transaksi->created_at ? \Carbon\Carbon::parse($transaksi->created_at)->translatedFormat('d F Y H:i:s') : '-' }}</span>
                </div>

                <div class="flex justify-between py-1.5">
                    <span class="text-zinc-500">Batas Waktu (Expired):</span>
                    <span class="font-semibold text-rose-600 dark:text-rose-400">{{ $transaksi->expired_at ? \Carbon\Carbon::parse($transaksi->expired_at)->translatedFormat('d F Y H:i:s') : '-' }}</span>
                </div>
            </div>
        </flux:card>

        <!-- Informasi Tagihan Terkait -->
        <flux:card class="p-6 space-y-4">
            <flux:heading size="md">Invoice & Pelanggan Terkait</flux:heading>

            @if($transaksi->invoice)
                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="text-zinc-500">Nomor Invoice:</span>
                        <a href="{{ route('invoice.show', $transaksi->invoice) }}" class="font-mono font-bold text-indigo-600 dark:text-indigo-400 hover:underline">
                            {{ $transaksi->invoice->no_invoice }}
                        </a>
                    </div>

                    <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="text-zinc-500">Status Invoice:</span>
                        <flux:badge size="sm" variant="pill" :color="$transaksi->invoice->status->color()">
                            {{ $transaksi->invoice->status->label() }}
                        </flux:badge>
                    </div>

                    <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="text-zinc-500">Pelanggan:</span>
                        <span class="font-semibold">{{ $transaksi->invoice->pelanggan?->namaLengkap() }}</span>
                    </div>

                    <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="text-zinc-500">No. Registrasi:</span>
                        <span class="font-mono">{{ $transaksi->invoice->pelanggan?->no_reg }}</span>
                    </div>

                    <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="text-zinc-500">Nomor Telepon:</span>
                        <span>{{ $transaksi->invoice->pelanggan?->no_hp }}</span>
                    </div>

                    <div class="flex justify-between py-1.5">
                        <span class="text-zinc-500">Jumlah Tagihan Pokok:</span>
                        <span class="font-semibold">{{ $transaksi->invoice->formattedJumlahSetelahPromo() }}</span>
                    </div>
                </div>
            @endif
        </flux:card>
    </div>

    <!-- Riwayat Log Webhook -->
    <flux:card class="p-6 space-y-4">
        <flux:heading size="md">Log Webhook Masuk (Audit Trail)</flux:heading>

        @if($transaksi->webhookLogs->isEmpty())
            <div class="p-4 text-center text-xs text-zinc-400">
                Belum ada webhook callback yang diterima untuk transaksi ini.
            </div>
        @else
            <div class="space-y-3">
                @foreach($transaksi->webhookLogs as $log)
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700/60 text-xs space-y-2">
                        <div class="flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <span class="font-mono font-bold">{{ $log->event_type }}</span>
                                <flux:badge size="xs" variant="pill" :color="$log->status_proses->color()">
                                    {{ $log->status_proses->label() }}
                                </flux:badge>
                            </div>
                            <span class="text-[11px] text-zinc-400">
                                Diterima: {{ \Carbon\Carbon::parse($log->diterima_pada)->translatedFormat('d M Y H:i:s') }}
                            </span>
                        </div>

                        @if($log->catatan_error)
                            <div class="text-rose-600 dark:text-rose-400 font-medium">
                                Error: {{ $log->catatan_error }}
                            </div>
                        @endif

                        <details class="cursor-pointer text-[11px] text-zinc-500">
                            <summary class="hover:text-zinc-800 dark:hover:text-zinc-200">Lihat Raw Payload JSON</summary>
                            <pre class="mt-2 p-3 bg-white dark:bg-zinc-900 rounded-lg overflow-x-auto font-mono text-[10px]">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>
</div>
