<div>
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Daftar Tagihan (Invoice)</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Kelola dan pantau seluruh tagihan langganan internet pelanggan.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('invoice.buat')
                <flux:button href="{{ route('invoice.create') }}" variant="primary" icon="plus" wire:navigate>
                    Terbitkan Tagihan Baru
                </flux:button>
            @endcan
        </div>
    </div>

    <!-- Filter & Search Bar -->
    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6 flex flex-col sm:flex-row gap-4 justify-between items-center">
        <div class="w-full sm:w-80">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari No. Invoice, Pelanggan, Site ID..."
                clearable
            />
        </div>
        <div class="w-full sm:w-56">
            <flux:select wire:model.live="status" placeholder="Semua Status">
                <flux:select.option value="">Semua Status</flux:select.option>
                @foreach($statuses as $st)
                    <flux:select.option value="{{ $st->value }}">{{ $st->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Table -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium">
                    <tr>
                        <th class="px-4 py-3">No. Invoice</th>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Layanan / Paket</th>
                        <th class="px-4 py-3 text-right">Total Tagihan</th>
                        <th class="px-4 py-3">Jatuh Tempo</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($invoices as $inv)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">
                                <a href="{{ route('invoice.show', $inv) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400">
                                    {{ $inv->no_invoice }}
                                </a>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">{{ $inv->pelanggan?->nama_lengkap ?? '-' }}</div>
                                <div class="text-xs text-zinc-500">{{ $inv->pelanggan?->no_reg }} • {{ $inv->pelanggan?->no_hp }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-zinc-900 dark:text-white font-medium">{{ $inv->layananPelanggan?->paketLayanan?->nama_paket ?? '-' }}</div>
                                <div class="text-xs text-zinc-500 font-mono">Site: {{ $inv->layananPelanggan?->site_id }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-semibold text-zinc-900 dark:text-white">
                                {{ $inv->formattedJumlahSetelahPromo() }}
                                @if($inv->promo)
                                    <div class="text-xs text-emerald-600 dark:text-emerald-400 font-normal">Promo: {{ $inv->promo->kode_promo }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="text-zinc-700 dark:text-zinc-300">{{ $inv->tanggal_jatuh_tempo->format('d M Y') }}</div>
                                <div class="text-xs text-zinc-500">Terbit: {{ $inv->tanggal_terbit->format('d M Y') }}</div>
                            </td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" :color="$inv->status->color()">
                                    {{ $inv->status->label() }}
                                </flux:badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button href="{{ route('invoice.show', $inv) }}" size="xs" variant="subtle" icon="eye" wire:navigate title="Lihat Detail" />
                                    @can('invoice.cetak')
                                        <flux:button href="{{ route('invoice.cetak', $inv) }}" target="_blank" size="xs" variant="subtle" icon="printer" title="Cetak PDF" />
                                    @endcan
                                    @can('invoice.hapus')
                                        @if(!$inv->isLunas())
                                            <flux:button wire:click="confirmDelete({{ $inv->id }})" size="xs" variant="danger" icon="trash" title="Batalkan Tagihan" />
                                        @endif
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-zinc-500 dark:text-zinc-400">
                                Tidak ada data tagihan yang ditemukan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($invoices->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $invoices->links() }}
            </div>
        @endif
    </div>

    <!-- Modal Konfirmasi Hapus / Batal -->
    <flux:modal :open="$deletingId !== null" wire:model.self="deletingId" class="max-w-md">
        <div class="p-6 space-y-4">
            <div class="flex items-center gap-3 text-rose-600">
                <flux:icon name="exclamation-triangle" class="size-6" />
                <h3 class="text-lg font-bold">Batalkan Tagihan?</h3>
            </div>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">
                Apakah Anda yakin ingin membatalkan tagihan invoice ini? Status tagihan akan diubah menjadi <strong>Dibatalkan</strong>.
            </p>
            <flux:textarea wire:model="keteranganHapus" label="Alasan Pembatalan (Opsional)" placeholder="Contoh: Kesalahan nominal atau permohonan pelanggan..." rows="2" />
            <div class="flex justify-end gap-2 pt-2">
                <flux:button wire:click="$set('deletingId', null)" variant="subtle">Batal</flux:button>
                <flux:button wire:click="deleteInvoice" variant="danger">Ya, Batalkan Invoice</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
