<div class="space-y-6" @if ($autoRefresh) wire:poll.4s @endif>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <flux:heading size="xl">Log Integrasi MikroTik</flux:heading>
                @if ($autoRefresh)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-400 border border-emerald-200/60 dark:border-emerald-800/60">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        Realtime Live
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-zinc-100 px-2.5 py-0.5 text-xs font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-400">
                        <span class="h-2 w-2 rounded-full bg-zinc-400"></span>
                        Jeda
                    </span>
                @endif
            </div>
            <flux:subheading>Riwayat antrean job RouterOS API: provisi PPPoE, isolir, sinkronisasi IP Pool, uji koneksi, dan ping perangkat.</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button
                wire:click="toggleAutoRefresh"
                size="sm"
                variant="{{ $autoRefresh ? 'subtle' : 'outline' }}"
                icon="{{ $autoRefresh ? 'pause' : 'play' }}"
            >
                {{ $autoRefresh ? 'Jeda Realtime' : 'Aktifkan Realtime' }}
            </flux:button>
            <flux:button
                wire:click="refreshData"
                wire:loading.attr="disabled"
                size="sm"
                variant="outline"
                icon="arrow-path"
            >
                Refresh
            </flux:button>
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-4">
        <div>
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari user, pool, error..."
            />
        </div>

        <div>
            <flux:select wire:model.live="filterRouter" placeholder="Semua Router">
                <flux:select.option value="">Semua Router</flux:select.option>
                @foreach ($routers as $router)
                    <flux:select.option value="{{ $router->id }}">{{ $router->nama_router }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div>
            <flux:select wire:model.live="filterJobType" placeholder="Semua Jenis Job">
                <flux:select.option value="">Semua Jenis Job</flux:select.option>
                @foreach ($jobTypes as $type)
                    <flux:select.option value="{{ $type->value }}">{{ $type->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div>
            <flux:select wire:model.live="filterStatus" placeholder="Semua Status">
                <flux:select.option value="">Semua Status</flux:select.option>
                @foreach ($statuses as $status)
                    <flux:select.option value="{{ $status->value }}">{{ $status->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    {{-- Tabel Log Integrasi --}}
    <flux:table>
        <flux:table.columns>
            <flux:table.column>Waktu</flux:table.column>
            <flux:table.column>Router</flux:table.column>
            <flux:table.column>Jenis Job</flux:table.column>
            <flux:table.column>Target Entitas</flux:table.column>
            <flux:table.column>Status / Percobaan</flux:table.column>
            <flux:table.column>Pesan / Error</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($logs as $log)
                <flux:table.row :key="$log->id">
                    {{-- Waktu --}}
                    <flux:table.cell class="whitespace-nowrap text-xs text-zinc-500">
                        {{ $log->created_at->format('d/m/Y H:i:s') }}
                    </flux:table.cell>

                    {{-- Router --}}
                    <flux:table.cell>
                        <span class="font-medium text-zinc-800 dark:text-zinc-200">
                            {{ $log->router?->nama_router ?? '-' }}
                        </span>
                    </flux:table.cell>

                    {{-- Jenis Job --}}
                    <flux:table.cell>
                        <flux:badge size="sm" color="zinc">
                            {{ $log->job_type->label() }}
                        </flux:badge>
                    </flux:table.cell>

                    {{-- Target Entitas --}}
                    <flux:table.cell>
                        @if ($log->layananPelanggan)
                            <div class="flex flex-col">
                                <span class="font-mono text-xs font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ $log->layananPelanggan->ppp_username }}
                                </span>
                                <span class="text-[11px] text-zinc-500">
                                    {{ $log->layananPelanggan->pelanggan?->identitasLengkap() ?? '-' }}
                                </span>
                            </div>
                        @elseif ($log->ipPool)
                            <span class="font-mono text-xs text-zinc-800 dark:text-zinc-200">
                                Pool: {{ $log->ipPool->nama_pool }}
                            </span>
                        @else
                            <span class="text-xs text-zinc-400">Router System</span>
                        @endif
                    </flux:table.cell>

                    {{-- Status & Attempt --}}
                    <flux:table.cell>
                        <div class="flex items-center gap-1.5">
                            <flux:badge size="sm" :color="$log->status->color()">
                                {{ $log->status->label() }}
                            </flux:badge>
                            @if ($log->attempt_count > 1)
                                <span class="text-[11px] text-zinc-400 font-mono">
                                    (x{{ $log->attempt_count }})
                                </span>
                            @endif
                        </div>
                    </flux:table.cell>

                    {{-- Pesan / Error --}}
                    <flux:table.cell class="max-w-xs truncate text-xs">
                        @if ($log->error_message)
                            <span class="text-red-600 dark:text-red-400 font-mono" title="{{ $log->error_message }}">
                                {{ $log->error_message }}
                            </span>
                        @elseif ($log->status === \App\Enums\MikrotikJobStatus::Success)
                            <span class="text-emerald-600 dark:text-emerald-400">
                                Selesai dengan sukses
                            </span>
                        @else
                            <span class="text-zinc-400">Sedang diproses</span>
                        @endif
                    </flux:table.cell>

                    {{-- Aksi Retry --}}
                    <flux:table.cell align="end">
                        @if ($log->status === \App\Enums\MikrotikJobStatus::Failed)
                            <flux:button
                                wire:click="retryJob({{ $log->id }})"
                                wire:loading.attr="disabled"
                                size="sm"
                                variant="subtle"
                                icon="arrow-path"
                                class="text-indigo-600 hover:text-indigo-700"
                                title="Jalankan Ulang Job Ini"
                            >
                                Retry
                            </flux:button>
                        @endif
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="7" class="py-12 text-center text-zinc-500">
                        <div class="flex flex-col items-center gap-2">
                            <flux:icon name="queue-list" class="size-8 text-zinc-300 dark:text-zinc-600" />
                            <p class="font-medium">Tidak ada log job MikroTik ditemukan.</p>
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    {{-- Pagination --}}
    @if ($logs->hasPages())
        <div>
            {{ $logs->links() }}
        </div>
    @endif
</div>
