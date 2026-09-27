<div class="grid grid-cols-1 gap-6 lg:grid-cols-2 items-start" wire:poll.120s>
    @if ($router)
        <x-dashboard-panel judul="Ringkasan Router" subjudul="Router terdaftar, yang terkoneksi, dan yang offline.">
            <x-slot:aksi>
                <flux:button :href="route('router.index')" variant="ghost" size="xs" wire:navigate>Lihat semua &rarr;</flux:button>
            </x-slot:aksi>

            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $router['total'] }}</span>
                <span class="text-sm text-zinc-500">router</span>
            </div>
            <x-bar-distribusi :segmen="[
                ['label' => 'Online', 'nilai' => $router['online'], 'warna' => 'emerald'],
                ['label' => 'Offline / belum dicek', 'nilai' => $router['offline'], 'warna' => 'rose'],
            ]" />

            @if ($router['daftar_offline']->isNotEmpty())
                <div class="divide-y divide-zinc-100 dark:divide-zinc-700/60">
                    @foreach ($router['daftar_offline'] as $r)
                        <div wire:key="router-offline-{{ $r->id }}" class="py-2 flex items-center justify-between gap-3 text-xs">
                            <div class="min-w-0">
                                <div class="font-semibold text-zinc-900 dark:text-zinc-100 truncate">{{ $r->nama_router }}</div>
                                <div class="font-mono text-[11px] text-zinc-500">{{ $r->ip_address }}</div>
                            </div>
                            <div class="shrink-0 text-right">
                                <flux:badge size="sm" :color="$r->status_koneksi->color()">{{ $r->status_koneksi->label() }}</flux:badge>
                                <div class="mt-0.5 text-[11px] text-zinc-400">{{ $r->last_ping_at?->diffForHumans() ?? 'belum pernah dicek' }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-dashboard-panel>
    @endif

    @if (array_filter($konfigurasi, fn ($nilai) => $nilai !== null))
        <x-dashboard-panel judul="Paket & Jaringan" subjudul="Paket layanan, profil bandwidth, dan IP Pool yang terdaftar.">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                @foreach ([
                    ['nilai' => $konfigurasi['paket_aktif'], 'label' => 'Paket aktif', 'catatan' => $konfigurasi['paket_total'] !== null ? 'dari ' . $konfigurasi['paket_total'] . ' paket' : null, 'route' => 'paket-layanan.index', 'icon' => 'cube'],
                    ['nilai' => $konfigurasi['profil'], 'label' => 'Profil bandwidth', 'catatan' => null, 'route' => 'profil-bandwidth.index', 'icon' => 'signal'],
                    ['nilai' => $konfigurasi['ip_pool'], 'label' => 'IP Pool', 'catatan' => null, 'route' => 'ip-pool.index', 'icon' => 'globe-alt'],
                ] as $item)
                    @if ($item['nilai'] !== null)
                        <a href="{{ route($item['route']) }}" wire:navigate
                            class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-3 transition hover:border-zinc-300 dark:hover:border-zinc-600">
                            <flux:icon :name="$item['icon']" class="size-5 text-zinc-400" />
                            <div class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">{{ $item['nilai'] }}</div>
                            <div class="text-xs text-zinc-500">{{ $item['label'] }}</div>
                            @if ($item['catatan'])
                                <div class="text-[11px] text-zinc-400">{{ $item['catatan'] }}</div>
                            @endif
                        </a>
                    @endif
                @endforeach
            </div>
        </x-dashboard-panel>
    @endif
</div>
