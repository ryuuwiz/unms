<div>
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Daftar Tiket Operasional</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Manajemen tiket pemasangan, gangguan, pencabutan, dan pemindahan alamat layanan.</p>
        </div>
        <div class="flex items-center gap-2">
            @can('ticket.buat')
                <flux:button href="{{ route('ticket.create') }}" variant="primary" icon="plus" wire:navigate>
                    Buat Tiket Baru
                </flux:button>
            @endcan
        </div>
    </div>

    <!-- Metrik Ringkasan Tiket -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded-xl">
                <flux:icon name="ticket" class="size-6" />
            </div>
            <div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($totalAktif) }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Total Tiket Aktif</div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 rounded-xl">
                <flux:icon name="user-plus" class="size-6" />
            </div>
            <div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($menungguPic) }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Menunggu PIC</div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 rounded-xl">
                <flux:icon name="arrow-path" class="size-6" />
            </div>
            <div>
                <div class="text-2xl font-bold text-zinc-900 dark:text-white">{{ number_format($dalamProses) }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">Sedang Diproses</div>
            </div>
        </div>

        <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-rose-50 dark:bg-rose-900/30 text-rose-600 dark:text-rose-400 rounded-xl">
                <flux:icon name="clock" class="size-6" />
            </div>
            <div>
                <div class="text-2xl font-bold text-rose-600 dark:text-rose-400">{{ number_format($overdueCount) }}</div>
                <div class="text-xs text-zinc-500 dark:text-zinc-400">SLA Overdue</div>
            </div>
        </div>
    </div>

    <!-- Tabs & Filter Bar -->
    <div class="bg-white dark:bg-zinc-800 p-4 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm mb-6 space-y-4">
        <!-- Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-zinc-200 dark:border-zinc-700">
            <button
                wire:click="setTab('semua')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap {{ $tab === 'semua' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700/50' }}"
            >
                Semua Tiket
            </button>
            <button
                wire:click="setTab('saya')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap {{ $tab === 'saya' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700/50' }}"
            >
                Tiket Saya (PIC)
            </button>
            <button
                wire:click="setTab('baru')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap {{ $tab === 'baru' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700/50' }}"
            >
                Baru
            </button>
            <button
                wire:click="setTab('diproses')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap {{ $tab === 'diproses' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700/50' }}"
            >
                Diproses
            </button>
            <button
                wire:click="setTab('menunggu_konfirmasi')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap {{ $tab === 'menunggu_konfirmasi' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700/50' }}"
            >
                Menunggu Konfirmasi
            </button>
            <button
                wire:click="setTab('selesai')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap {{ $tab === 'selesai' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700/50' }}"
            >
                Selesai
            </button>
            <button
                wire:click="setTab('overdue')"
                class="px-3 py-1.5 text-xs font-semibold rounded-lg transition-colors whitespace-nowrap {{ $tab === 'overdue' ? 'bg-rose-600 text-white' : 'text-rose-600 dark:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-900/30' }}"
            >
                Overdue SLA @if($overdueCount > 0) ({{ $overdueCount }}) @endif
            </button>
        </div>

        <!-- Filter Fields -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <flux:input
                    wire:model.live.debounce.300ms="search"
                    icon="magnifying-glass"
                    placeholder="Cari No. Tiket, Pelanggan, No. Reg, PPP..."
                    clearable
                />
            </div>
            <div>
                <flux:select wire:model.live="jenis" placeholder="Semua Jenis">
                    <flux:select.option value="">Semua Jenis</flux:select.option>
                    @foreach($jenisList as $j)
                        <flux:select.option value="{{ $j->value }}">{{ $j->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="prioritas" placeholder="Semua Prioritas">
                    <flux:select.option value="">Semua Prioritas</flux:select.option>
                    @foreach($prioritasList as $p)
                        <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <div>
                <flux:select wire:model.live="divisi" placeholder="Semua Divisi">
                    <flux:select.option value="">Semua Divisi</flux:select.option>
                    @foreach($divisiList as $d)
                        <flux:select.option value="{{ $d->value }}">{{ $d->label() }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
        </div>
    </div>

    <!-- Table (Presisi Mapping data_unms.md) -->
    <div class="bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-900/50 border-b border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-400 font-medium">
                    <tr>
                        <th class="px-4 py-3">ID Tiket</th>
                        <th class="px-4 py-3">Waktu & SLA</th>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3">Layanan</th>
                        <th class="px-4 py-3">Jenis</th>
                        <th class="px-4 py-3">Prioritas / Divisi</th>
                        <th class="px-4 py-3">PIC</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @forelse($tickets as $tck)
                        <tr class="hover:bg-zinc-50 dark:hover:bg-zinc-700/30 transition-colors">
                            <!-- ID -->
                            <td class="px-4 py-3 font-semibold text-zinc-900 dark:text-white">
                                <a href="{{ route('ticket.show', $tck) }}" wire:navigate class="hover:text-blue-600 dark:hover:text-blue-400 font-mono">
                                    {{ $tck->nomor_ticket }}
                                </a>
                                @if($tck->perlu_aktivasi_manual)
                                    <div class="mt-0.5">
                                        <flux:badge size="xs" color="amber" icon="exclamation-circle">Perlu Aktivasi</flux:badge>
                                    </div>
                                @endif
                            </td>

                            <!-- Waktu & SLA -->
                            <td class="px-4 py-3">
                                <div class="text-zinc-800 dark:text-zinc-200 text-xs">
                                    Dibuat: {{ $tck->created_at->format('d/m/Y H:i') }}
                                </div>
                                <div class="text-xs mt-0.5 flex items-center gap-1 font-medium {{ $tck->isOverdue() ? 'text-rose-600 dark:text-rose-400 font-semibold' : 'text-zinc-500 dark:text-zinc-400' }}">
                                    <flux:icon name="clock" class="size-3" />
                                    SLA: {{ $tck->sisaWaktuSla() }}
                                </div>
                            </td>

                            <!-- Pelanggan -->
                            <td class="px-4 py-3">
                                <div class="font-medium text-zinc-900 dark:text-white">
                                    {{ $tck->pelanggan?->identitasLengkap() ?? '-' }}
                                </div>
                                <div class="text-xs text-zinc-500">
                                    {{ $tck->pelanggan?->no_hp }}
                                </div>
                            </td>

                            <!-- Layanan -->
                            <td class="px-4 py-3">
                                @if($tck->layananPelanggan)
                                    <div class="font-medium text-zinc-900 dark:text-white font-mono text-xs">
                                        {{ $tck->layananPelanggan->ppp_username }}
                                    </div>
                                    <div class="text-xs text-zinc-500">
                                        {{ strtoupper($tck->layananPelanggan->jenis_koneksi->value ?? 'PPPOE') }} • Site: {{ $tck->layananPelanggan->site_id }}
                                    </div>
                                @else
                                    <span class="text-xs text-zinc-400 italic">Pemasangan Baru / Prospek</span>
                                @endif
                            </td>

                            <!-- Jenis -->
                            <td class="px-4 py-3">
                                <flux:badge size="sm" :color="$tck->jenis->color()" :icon="$tck->jenis->icon()">
                                    {{ $tck->jenis->label() }}
                                </flux:badge>
                            </td>

                            <!-- Prioritas / Divisi -->
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1 items-start">
                                    <flux:badge size="xs" :color="$tck->prioritas->color()">
                                        {{ $tck->prioritas->label() }}
                                    </flux:badge>
                                    <span class="text-xs text-zinc-500 font-medium">
                                        {{ $tck->divisi->label() }}
                                    </span>
                                </div>
                            </td>

                            <!-- PIC -->
                            <td class="px-4 py-3">
                                @if($tck->pic)
                                    <div class="flex items-center gap-1.5">
                                        <flux:avatar size="xs" :name="$tck->pic->name" :initials="$tck->pic->initials()" />
                                        <span class="text-xs font-medium text-zinc-800 dark:text-zinc-200 truncate max-w-[120px]">
                                            {{ $tck->pic->name }}
                                        </span>
                                    </div>
                                @else
                                    <flux:badge size="xs" color="zinc" icon="user-minus">
                                        Belum Ada
                                    </flux:badge>
                                @endif
                            </td>

                            <!-- Status -->
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" :color="$tck->status->color()">
                                    {{ $tck->status->label() }}
                                </flux:badge>
                            </td>

                            <!-- Aksi -->
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-1">
                                    <flux:button href="{{ route('ticket.show', $tck) }}" size="xs" variant="subtle" icon="eye" wire:navigate title="Lihat Detail Tiket" />
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-12 text-center text-zinc-500 dark:text-zinc-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <flux:icon name="ticket" class="size-8 text-zinc-400" />
                                    <span>Tidak ada data tiket yang ditemukan.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($tickets->hasPages())
            <div class="p-4 border-t border-zinc-200 dark:border-zinc-700">
                {{ $tickets->links() }}
            </div>
        @endif
    </div>
</div>
