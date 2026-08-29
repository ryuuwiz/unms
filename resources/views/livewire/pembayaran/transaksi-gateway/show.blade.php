<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="{{ route('pembayaran.transaksi-gateway.index') }}" wire:navigate class="text-xs font-semibold text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 flex items-center gap-1">
                &larr; Kembali ke Daftar Transaksi Gateway
            </a>
            <div class="flex items-center gap-2 mt-2">
                <flux:heading size="xl">Detail Transaksi Gateway</flux:heading>
                <flux:badge size="sm" variant="pill" :color="$transaksi->status->color()">
                    {{ $transaksi->status->label() }}
                </flux:badge>
            </div>
            <flux:subheading font-mono class="text-xs">{{ $transaksi->external_id }}</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(($transaksi->status === \App\Enums\StatusTransaksiGateway::Pending) && (\App\Models\PengaturanGateway::getXenditSetting()->sandbox_mode || app()->environment('local', 'testing')))
                <flux:button wire:click="simulasikanPembayaran" size="sm" variant="primary" icon="bolt" wire:loading.attr="disabled">
                    Simulasikan Pembayaran (Sandbox)
                </flux:button>
            @endif

            <flux:button wire:click="rekonsiliasiStatus" size="sm" variant="ghost" icon="arrow-path" wire:loading.attr="disabled">
                Rekonsiliasi Status Gateway
            </flux:button>
        </div>
    </div>

    @if($reconciliationResult)
        <flux:card class="p-4 bg-indigo-50/70 dark:bg-indigo-950/40 border-indigo-200 dark:border-indigo-800 text-xs">
            <div class="flex justify-between items-center mb-1.5">
                <div class="font-bold text-indigo-900 dark:text-indigo-200 flex items-center gap-1.5">
                    <flux:icon.arrow-path class="size-4 text-indigo-600 dark:text-indigo-400" />
                    Hasil Rekonsiliasi API {{ strtoupper($transaksi->gateway) }}:
                </div>
                <span class="text-[11px] text-indigo-700 dark:text-indigo-300">Sinkronisasi Berhasil</span>
            </div>
            <pre class="overflow-x-auto text-[11px] p-3 bg-white dark:bg-zinc-900/90 rounded-lg font-mono border border-indigo-100 dark:border-indigo-900/50">{{ json_encode($reconciliationResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
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
                    <span class="text-zinc-500">Gateway Provider:</span>
                    <span class="font-semibold uppercase text-zinc-900 dark:text-zinc-100">{{ $transaksi->gateway }}</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Kanal Pembayaran:</span>
                    <span class="font-semibold">{{ $transaksi->channel->label() }} ({{ strtoupper($transaksi->channel_detail ?? '-') }})</span>
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Nomor Pembayaran (VA/URL/QR):</span>
                    @if(str_starts_with($transaksi->nomor_pembayaran ?? '', 'http'))
                        <a href="{{ $transaksi->nomor_pembayaran }}" target="_blank" class="font-mono text-xs text-indigo-600 dark:text-indigo-400 hover:underline max-w-[240px] truncate">
                            {{ $transaksi->nomor_pembayaran }} &nearr;
                        </a>
                    @else
                        <span class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ $transaksi->nomor_pembayaran ?? '-' }}</span>
                    @endif
                </div>

                <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                    <span class="text-zinc-500">Provider Reference ID:</span>
                    <span class="font-mono text-zinc-800 dark:text-zinc-200">{{ $transaksi->provider_reference_id ?: ($transaksi->xendit_reference_id ?? '-') }}</span>
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
                    <span class="text-zinc-500">Waktu Sesi Dibuat:</span>
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
                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">{{ $transaksi->invoice->pelanggan?->namaLengkap() }}</span>
                    </div>

                    <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="text-zinc-500">No. Registrasi:</span>
                        <span class="font-mono">{{ $transaksi->invoice->pelanggan?->no_reg }}</span>
                    </div>

                    <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="text-zinc-500">Nomor Telepon:</span>
                        <span>{{ $transaksi->invoice->pelanggan?->no_hp ?? '-' }}</span>
                    </div>

                    <div class="flex justify-between py-1.5 border-b border-zinc-100 dark:border-zinc-800">
                        <span class="text-zinc-500">Email Pelanggan:</span>
                        <span>{{ $transaksi->invoice->pelanggan?->email ?? '-' }}</span>
                    </div>

                    <div class="flex justify-between py-1.5">
                        <span class="text-zinc-500">Jumlah Tagihan Pokok:</span>
                        <span class="font-semibold">{{ $transaksi->invoice->formattedJumlahSetelahPromo() }}</span>
                    </div>
                </div>
            @else
                <div class="p-4 text-center text-xs text-zinc-400">
                    Data invoice terkait tidak ditemukan atau telah dihapus.
                </div>
            @endif
        </flux:card>
    </div>

    <!-- Respon & Request Gateway API (Trx Responses) -->
    <flux:card class="p-6 space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <flux:heading size="md">Respon Gateway & Data Sesi (Trx Responses)</flux:heading>
                <flux:subheading class="text-xs">Data transaksi dan respon payload resmi dari gateway payment.</flux:subheading>
            </div>
            <div class="flex items-center gap-2 text-xs">
                @if(!empty($transaksi->payload_response))
                    <flux:badge size="xs" variant="pill" color="emerald">Response API Tersedia</flux:badge>
                @else
                    <flux:badge size="xs" variant="pill" color="zinc">Belum Ada Response API</flux:badge>
                @endif
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Request Payload -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700/60 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Payload Request Gateway</span>
                    <span class="text-[10px] font-mono text-zinc-400">Outbound ke API {{ strtoupper($transaksi->gateway) }}</span>
                </div>
                @if(!empty($transaksi->payload_request))
                    <pre class="p-3 bg-white dark:bg-zinc-900 rounded-lg overflow-x-auto font-mono text-[11px] text-zinc-800 dark:text-zinc-200 border border-zinc-100 dark:border-zinc-800 max-h-64">{{ json_encode($transaksi->payload_request, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <div class="p-4 text-center text-xs text-zinc-400 bg-white/50 dark:bg-zinc-900/50 rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700">
                        Tidak ada catatan request payload tersimpan.
                    </div>
                @endif
            </div>

            <!-- Response Payload -->
            <div class="p-4 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700/60 space-y-3">
                <div class="flex justify-between items-center">
                    <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">Respon Gateway (Trx Response)</span>
                    <span class="text-[10px] font-mono text-zinc-400">Inbound dari API {{ strtoupper($transaksi->gateway) }}</span>
                </div>
                @if(!empty($transaksi->payload_response))
                    <pre class="p-3 bg-white dark:bg-zinc-900 rounded-lg overflow-x-auto font-mono text-[11px] text-zinc-800 dark:text-zinc-200 border border-zinc-100 dark:border-zinc-800 max-h-64">{{ json_encode($transaksi->payload_response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                @else
                    <div class="p-4 text-center text-xs text-zinc-400 bg-white/50 dark:bg-zinc-900/50 rounded-lg border border-dashed border-zinc-200 dark:border-zinc-700">
                        Belum ada data respon dari gateway.
                    </div>
                @endif
            </div>
        </div>
    </flux:card>

    <!-- Riwayat Log Webhook Masuk (Audit Trail) -->
    <flux:card class="p-6 space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <flux:heading size="md">Log Webhook Masuk (Audit Trail)</flux:heading>
                <flux:subheading class="text-xs">Catatan audit log kronologis penerimaan webhook dan sinkronisasi pembayaran.</flux:subheading>
            </div>
            <flux:badge size="sm" variant="pill" color="zinc">
                {{ $transaksi->webhookLogs->count() }} Event
            </flux:badge>
        </div>

        @if($transaksi->webhookLogs->isEmpty())
            <div class="p-8 text-center text-xs text-zinc-400 bg-zinc-50 dark:bg-zinc-800/40 rounded-xl border border-dashed border-zinc-200 dark:border-zinc-700">
                <flux:icon.receipt-refund class="size-8 mx-auto mb-2 text-zinc-300 dark:text-zinc-600" />
                Belum ada webhook callback yang diterima untuk transaksi ini.
            </div>
        @else
            <div class="space-y-3">
                @foreach($transaksi->webhookLogs as $log)
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/60 rounded-xl border border-zinc-200 dark:border-zinc-700/60 text-xs space-y-3">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono font-bold text-zinc-900 dark:text-zinc-100">{{ $log->event_type }}</span>
                                <flux:badge size="xs" variant="pill" color="indigo">
                                    {{ strtoupper($log->provider ?: 'xendit') }}
                                </flux:badge>
                                <flux:badge size="xs" variant="pill" :color="$log->status_proses->color()">
                                    {{ $log->status_proses->label() }}
                                </flux:badge>
                            </div>
                            <span class="text-[11px] text-zinc-500 dark:text-zinc-400">
                                Diterima: {{ \Carbon\Carbon::parse($log->diterima_pada)->translatedFormat('d M Y H:i:s') }}
                            </span>
                        </div>

                        <!-- Ringkasan Nilai Callback -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 p-2.5 bg-white dark:bg-zinc-900/70 rounded-lg border border-zinc-100 dark:border-zinc-800 text-[11px]">
                            <div>
                                <span class="text-zinc-400 block">Event ID:</span>
                                <span class="font-mono font-semibold text-zinc-800 dark:text-zinc-200 truncate block">{{ $log->provider_event_id ?: ($log->xendit_event_id ?? '-') }}</span>
                            </div>
                            <div>
                                <span class="text-zinc-400 block">Nominal Terbayar:</span>
                                <span class="font-semibold text-emerald-600 dark:text-emerald-400">
                                    Rp {{ number_format((float) ($log->payload['paid_amount'] ?? ($log->payload['amount'] ?? 0)), 0, ',', '.') }}
                                </span>
                            </div>
                            <div>
                                <span class="text-zinc-400 block">Metode / Kanal:</span>
                                <span class="font-semibold text-zinc-800 dark:text-zinc-200">
                                    {{ $log->payload['payment_method'] ?? '-' }} / {{ strtoupper((string) ($log->payload['payment_channel'] ?? '-')) }}
                                </span>
                            </div>
                            <div>
                                <span class="text-zinc-400 block">Status Gateway:</span>
                                <span class="font-bold text-zinc-800 dark:text-zinc-200">
                                    {{ $log->payload['status'] ?? '-' }}
                                </span>
                            </div>
                        </div>

                        @if($log->catatan_error)
                            <div class="p-2.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800 text-rose-700 dark:text-rose-300 rounded-lg font-medium text-[11px]">
                                <strong>Catatan / Error:</strong> {{ $log->catatan_error }}
                            </div>
                        @endif

                        <details class="cursor-pointer text-[11px] text-zinc-500">
                            <summary class="hover:text-zinc-800 dark:hover:text-zinc-200 font-medium">Lihat Raw Payload JSON</summary>
                            <pre class="mt-2 p-3 bg-white dark:bg-zinc-900 rounded-lg overflow-x-auto font-mono text-[10px] border border-zinc-100 dark:border-zinc-800">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </details>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>
</div>
