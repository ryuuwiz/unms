<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <flux:heading size="xl">Transaksi Payment Gateway</flux:heading>
            <flux:subheading>Monitoring transaksi pembayaran otomatis melalui Xendit (Virtual Account & QRIS)</flux:subheading>
        </div>
    </div>

    <!-- Filter Bar -->
    <flux:card class="p-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <flux:input
                wire:model.live.debounce.300ms="search"
                placeholder="Cari External ID, No. Invoice, Pelanggan, atau No. VA..."
                icon="magnifying-glass"
                clearable
            />

            <flux:select wire:model.live="channel" placeholder="Semua Kanal">
                <flux:select.option value="">Semua Kanal</flux:select.option>
                @foreach($channels as $c)
                    <flux:select.option :value="$c->value">{{ $c->label() }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" placeholder="Semua Status">
                <flux:select.option value="">Semua Status</flux:select.option>
                @foreach($statuses as $s)
                    <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <!-- Table -->
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3">External ID / Waktu</th>
                        <th class="px-4 py-3">Invoice & Pelanggan</th>
                        <th class="px-4 py-3">Kanal / Detail</th>
                        <th class="px-4 py-3">Nomor Pembayaran</th>
                        <th class="px-4 py-3 text-right">Total Tagihan</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($transaksis as $trx)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                            <td class="px-4 py-3">
                                <div class="font-mono font-bold text-zinc-900 dark:text-zinc-100 truncate max-w-48">
                                    {{ $trx->external_id }}
                                </div>
                                <div class="text-[11px] text-zinc-400">
                                    {{ $trx->created_at ? \Carbon\Carbon::parse($trx->created_at)->translatedFormat('d M Y H:i') : '-' }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                @if($trx->invoice)
                                    <a href="{{ route('invoice.show', $trx->invoice) }}" class="font-mono font-semibold text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ $trx->invoice->no_invoice }}
                                    </a>
                                    <div class="text-[11px] text-zinc-500">
                                        {{ $trx->invoice->pelanggan?->namaLengkap() }} ({{ $trx->invoice->pelanggan?->no_reg }})
                                    </div>
                                @else
                                    <span class="text-zinc-400">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <flux:badge size="sm" variant="pill" :color="$trx->channel->color()">
                                    {{ $trx->channel->label() }}
                                </flux:badge>
                                @if($trx->channel_detail)
                                    <div class="text-[11px] font-mono uppercase text-zinc-500 mt-0.5">
                                        {{ $trx->channel_detail }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono">
                                {{ $trx->nomor_pembayaran ?? ($trx->qr_string ? 'QRIS Dinamis' : '-') }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="font-bold text-zinc-900 dark:text-zinc-100">
                                    {{ $trx->formattedTotalTagihan() }}
                                </div>
                                @if($trx->fee_gateway > 0)
                                    <div class="text-[10px] text-zinc-400">
                                        Fee: {{ $trx->formattedFeeGateway() }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" variant="pill" :color="$trx->status->color()">
                                    {{ $trx->status->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:button :href="route('pembayaran.transaksi-gateway.show', $trx)" size="xs" variant="ghost" wire:navigate>
                                    Detail
                                </flux:button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-zinc-400">
                                Tidak ada data transaksi payment gateway yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($transaksis->hasPages())
            <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
                {{ $transaksis->links() }}
            </div>
        @endif
    </flux:card>
</div>
