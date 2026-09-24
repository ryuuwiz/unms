@php
    $tabList = [
        'semua' => 'Semua Tiket',
        'saya' => 'Tiket Saya (PIC)',
        'baru' => 'Baru',
        'diproses' => 'Diproses',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'selesai' => 'Selesai',
    ];
    // Kelas ditulis utuh (bukan disusun dari string) agar terbaca oleh Tailwind JIT.
    $kartuMetrik = [
        ['label' => 'Tiket Aktif', 'nilai' => $totalAktif, 'icon' => 'ticket', 'ikon' => 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400', 'angka' => 'text-zinc-900 dark:text-white'],
        ['label' => 'Menunggu PIC', 'nilai' => $menungguPic, 'icon' => 'user-plus', 'ikon' => 'bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400', 'angka' => 'text-zinc-900 dark:text-white'],
        ['label' => 'Diproses', 'nilai' => $dalamProses, 'icon' => 'arrow-path', 'ikon' => 'bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400', 'angka' => 'text-zinc-900 dark:text-white'],
        ['label' => 'SLA Overdue', 'nilai' => $overdueCount, 'icon' => 'clock', 'ikon' => 'bg-rose-50 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400', 'angka' => $overdueCount > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-zinc-900 dark:text-white'],
    ];
@endphp

<div>
    <!-- Header -->
    <div class="mb-4 flex flex-col justify-between gap-3 sm:mb-6 sm:flex-row sm:items-center">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 sm:text-2xl dark:text-white">Daftar Tiket Operasional</h1>
            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">Tiket pemasangan, gangguan, pencabutan, dan pemindahan alamat layanan.</p>
        </div>
        @can('ticket.buat')
            <flux:button href="{{ route('ticket.create') }}" variant="primary" icon="plus" wire:navigate class="w-full sm:w-auto">
                Buat Tiket Baru
            </flux:button>
        @endcan
    </div>

    <!-- Ringkasan: 2x2 di HP, 4 kolom di layar lebar -->
    <div class="mb-4 grid grid-cols-2 gap-3 sm:mb-6 lg:grid-cols-4">
        @foreach ($kartuMetrik as $kartu)
            <div class="flex items-center gap-3 rounded-xl border border-zinc-200 bg-white p-3 shadow-sm sm:p-4 dark:border-zinc-700 dark:bg-zinc-800">
                <div class="hidden rounded-xl p-3 sm:block {{ $kartu['ikon'] }}">
                    <flux:icon :name="$kartu['icon']" class="size-6" />
                </div>
                <div>
                    <div class="text-xl font-bold sm:text-2xl {{ $kartu['angka'] }}">{{ number_format($kartu['nilai']) }}</div>
                    <div class="text-xs text-zinc-500 dark:text-zinc-400">{{ $kartu['label'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Tab & Filter -->
    <div class="mb-4 space-y-3 rounded-xl border border-zinc-200 bg-white p-3 shadow-sm sm:mb-6 sm:p-4 dark:border-zinc-700 dark:bg-zinc-800" x-data="{ filterTerbuka: false }">
        <!-- Tab (status tiket): pemindai horizontal dengan tanda gradasi di tepi kanan pada HP -->
        <div class="relative">
            <div role="tablist" aria-label="Status tiket" class="-mx-1 flex items-center gap-1.5 overflow-x-auto px-1 pb-2">
                @foreach ($tabList as $kunci => $label)
                    <button
                        type="button"
                        role="tab"
                        aria-selected="{{ $tab === $kunci ? 'true' : 'false' }}"
                        wire:click="setTab('{{ $kunci }}')"
                        class="min-h-11 shrink-0 whitespace-nowrap rounded-lg px-4 text-sm font-semibold transition-colors {{ $tab === $kunci ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-700/50' }}"
                    >{{ $label }}</button>
                @endforeach
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $tab === 'overdue' ? 'true' : 'false' }}"
                    wire:click="setTab('overdue')"
                    class="inline-flex min-h-11 shrink-0 items-center gap-1 whitespace-nowrap rounded-lg px-4 text-sm font-semibold transition-colors {{ $tab === 'overdue' ? 'bg-rose-600 text-white' : 'text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/30' }}"
                >
                    <flux:icon name="exclamation-triangle" class="size-4" />
                    Overdue SLA @if ($overdueCount > 0) ({{ $overdueCount }}) @endif
                </button>
            </div>
            <div class="pointer-events-none absolute inset-y-0 right-0 w-10 bg-gradient-to-l from-white to-transparent md:hidden dark:from-zinc-800" aria-hidden="true"></div>
        </div>

        <!-- Cari + tombol Filter (HP) -->
        <div class="flex gap-2">
            <div class="flex-1">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="Cari No. Tiket, pelanggan, No. Reg, PPP..." clearable />
            </div>
            <flux:button x-on:click="filterTerbuka = ! filterTerbuka" icon="funnel" class="shrink-0 lg:hidden" x-bind:aria-expanded="filterTerbuka" aria-controls="panel-filter-tiket">
                Filter
                @if ($jumlahFilterAktif > 0)
                    <span class="ms-1 inline-flex size-5 items-center justify-center rounded-full bg-blue-600 text-xs font-semibold text-white">{{ $jumlahFilterAktif }}</span>
                @endif
            </flux:button>
        </div>

        <!-- Panel filter: tersembunyi di HP sampai tombol Filter ditekan, selalu tampil di layar lebar -->
        <div id="panel-filter-tiket" x-bind:class="filterTerbuka ? 'grid' : 'hidden lg:grid'" class="grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <flux:select wire:model.live="jenis" placeholder="Semua Jenis" aria-label="Filter jenis tiket">
                <flux:select.option value="">Semua Jenis</flux:select.option>
                @foreach ($jenisList as $j)
                    <flux:select.option value="{{ $j->value }}">{{ $j->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="prioritas" placeholder="Semua Prioritas" aria-label="Filter prioritas tiket">
                <flux:select.option value="">Semua Prioritas</flux:select.option>
                @foreach ($prioritasList as $p)
                    <flux:select.option value="{{ $p->value }}">{{ $p->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="divisi" placeholder="Semua Divisi" aria-label="Filter divisi tiket">
                <flux:select.option value="">Semua Divisi</flux:select.option>
                @foreach ($divisiList as $d)
                    <flux:select.option value="{{ $d->value }}">{{ $d->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="urut" aria-label="Urutan tiket">
                <flux:select.option value="prioritas">Urut: Prioritas & SLA</flux:select.option>
                <flux:select.option value="terbaru">Urut: Terbaru</flux:select.option>
            </flux:select>
        </div>

        @if ($jumlahFilterAktif > 0 || $search !== '')
            <div class="flex items-center justify-between text-sm text-zinc-500 dark:text-zinc-400">
                <span>{{ number_format($tickets->total()) }} tiket sesuai filter</span>
                <button type="button" wire:click="resetFilter" class="min-h-11 font-medium text-blue-600 hover:underline dark:text-blue-400">Reset filter</button>
            </div>
        @endif
    </div>

    <!-- Hasil: kartu di HP, tabel dari md ke atas -->
    <div wire:loading.class="opacity-60" wire:target="search,jenis,prioritas,divisi,urut,setTab,resetFilter,gotoPage,nextPage,previousPage" class="transition-opacity">
        @if ($tickets->isEmpty())
            <div class="flex flex-col items-center justify-center gap-2 rounded-xl border border-zinc-200 bg-white px-4 py-12 text-center text-zinc-500 shadow-sm dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400">
                <flux:icon name="ticket" class="size-8 text-zinc-400" />
                <span>
                    @if ($jumlahFilterAktif > 0 || $search !== '')
                        Tidak ada tiket yang cocok dengan pencarian/filter ini.
                    @else
                        Belum ada tiket di tab ini.
                    @endif
                </span>
                @if ($jumlahFilterAktif > 0 || $search !== '')
                    <flux:button size="sm" wire:click="resetFilter">Reset filter</flux:button>
                @endif
            </div>
        @else
            <!-- Kartu (HP) -->
            <div class="space-y-3 md:hidden">
                @foreach ($tickets as $tck)
                    <div wire:key="kartu-{{ $tck->id }}" class="relative rounded-xl border bg-white p-4 shadow-sm dark:bg-zinc-800 {{ $tck->isOverdue() ? 'border-rose-300 dark:border-rose-800' : 'border-zinc-200 dark:border-zinc-700' }}">
                        <div class="flex items-start justify-between gap-2">
                            <a href="{{ route('ticket.show', $tck) }}" wire:navigate class="font-mono text-sm font-semibold text-zinc-900 after:absolute after:inset-0 dark:text-white" aria-label="Buka tiket {{ $tck->nomor_ticket }}">
                                {{ $tck->nomor_ticket }}
                            </a>
                            <flux:badge size="sm" :color="$tck->status->color()">{{ $tck->status->label() }}</flux:badge>
                        </div>

                        <div class="mt-2 flex flex-wrap items-center gap-1.5">
                            <flux:badge size="sm" :color="$tck->jenis->color()" :icon="$tck->jenis->icon()">{{ $tck->jenis->label() }}</flux:badge>
                            <flux:badge size="sm" :color="$tck->prioritas->color()">{{ $tck->prioritas->label() }}</flux:badge>
                            @if ($tck->perlu_aktivasi_manual)
                                <flux:badge size="sm" color="amber" icon="exclamation-circle">Perlu Aktivasi</flux:badge>
                            @endif
                        </div>

                        <div class="mt-3">
                            <div class="text-base font-medium text-zinc-900 dark:text-white">{{ $tck->pelanggan?->identitasLengkap() ?? '-' }}</div>
                            <div class="mt-0.5 text-sm text-zinc-500 dark:text-zinc-400">
                                @if ($tck->layananPelanggan)
                                    <span class="font-mono">{{ $tck->layananPelanggan->ppp_username ?? 'Menunggu proses NOC' }}</span> · {{ $tck->layananPelanggan->site_id }}
                                @else
                                    Pemasangan baru / prospek
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                            @include('livewire.ticket.partials.sla', ['tck' => $tck])
                            <div class="flex items-center gap-1.5 text-sm text-zinc-600 dark:text-zinc-300">
                                <flux:icon name="user" class="size-4 text-zinc-400" />
                                {{ $tck->pic?->name ?? 'Belum ada PIC' }}
                            </div>
                        </div>

                        <div class="relative z-10 mt-3 border-t border-zinc-100 pt-3 dark:border-zinc-700">
                            @include('livewire.ticket.partials.aksi-cepat', ['tck' => $tck, 'ukuran' => 'base'])
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Tabel (md ke atas) -->
            <div class="hidden overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-sm md:block dark:border-zinc-700 dark:bg-zinc-800">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Daftar tiket operasional</caption>
                        <thead class="border-b border-zinc-200 bg-zinc-50 font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-400">
                            <tr>
                                <th scope="col" class="px-4 py-3">Tiket</th>
                                <th scope="col" class="px-4 py-3">Pelanggan & Layanan</th>
                                <th scope="col" class="px-4 py-3">Prioritas / Divisi</th>
                                <th scope="col" class="px-4 py-3">SLA</th>
                                <th scope="col" class="px-4 py-3">PIC</th>
                                <th scope="col" class="px-4 py-3">Status</th>
                                <th scope="col" class="px-4 py-3 text-right"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($tickets as $tck)
                                <tr wire:key="baris-{{ $tck->id }}" class="transition-colors hover:bg-zinc-50 dark:hover:bg-zinc-700/30 {{ $tck->isOverdue() ? 'bg-rose-50/50 dark:bg-rose-950/20' : '' }}">
                                    <td class="px-4 py-3 align-top">
                                        <a href="{{ route('ticket.show', $tck) }}" wire:navigate class="font-mono font-semibold text-zinc-900 hover:text-blue-600 dark:text-white dark:hover:text-blue-400">{{ $tck->nomor_ticket }}</a>
                                        <div class="mt-1"><flux:badge size="sm" :color="$tck->jenis->color()" :icon="$tck->jenis->icon()">{{ $tck->jenis->label() }}</flux:badge></div>
                                        @if ($tck->perlu_aktivasi_manual)
                                            <div class="mt-1"><flux:badge size="xs" color="amber" icon="exclamation-circle">Perlu Aktivasi</flux:badge></div>
                                        @endif
                                        <div class="mt-1 text-xs text-zinc-500">{{ $tck->created_at->format('d/m/Y H:i') }}</div>
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        <div class="font-medium text-zinc-900 dark:text-white">{{ $tck->pelanggan?->identitasLengkap() ?? '-' }}</div>
                                        <div class="text-xs text-zinc-500">{{ $tck->pelanggan?->no_hp }}</div>
                                        <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">
                                            @if ($tck->layananPelanggan)
                                                <span class="font-mono">{{ $tck->layananPelanggan->ppp_username ?? 'Menunggu proses NOC' }}</span> · {{ strtoupper($tck->layananPelanggan->jenis_koneksi->value) }} · {{ $tck->layananPelanggan->site_id }}
                                            @else
                                                <span class="italic text-zinc-400">Pemasangan baru / prospek</span>
                                            @endif
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 align-top">
                                        <flux:badge size="sm" :color="$tck->prioritas->color()">{{ $tck->prioritas->label() }}</flux:badge>
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($tck->divisis as $divisiItem)
                                                <span class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300">{{ $divisiItem->divisi->label() }}</span>
                                            @endforeach
                                        </div>
                                    </td>

                                    <td class="px-4 py-3 align-top">@include('livewire.ticket.partials.sla', ['tck' => $tck])</td>

                                    <td class="px-4 py-3 align-top">
                                        @if ($tck->pic)
                                            <div class="flex items-center gap-1.5">
                                                <flux:avatar size="xs" :name="$tck->pic->name" :initials="$tck->pic->initials()" />
                                                <span class="max-w-[9rem] truncate text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $tck->pic->name }}</span>
                                            </div>
                                        @else
                                            <flux:badge size="sm" color="zinc" icon="user-minus">Belum ada PIC</flux:badge>
                                        @endif
                                    </td>

                                    <td class="px-4 py-3 align-top"><flux:badge size="sm" :color="$tck->status->color()">{{ $tck->status->label() }}</flux:badge></td>

                                    <td class="px-4 py-3 align-top">
                                        <div class="flex justify-end">@include('livewire.ticket.partials.aksi-cepat', ['tck' => $tck, 'ukuran' => 'sm'])</div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($tickets->hasPages())
            <div class="mt-4">{{ $tickets->links() }}</div>
        @endif
    </div>
</div>
