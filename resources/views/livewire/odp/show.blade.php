<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <flux:heading size="xl">{{ $odp->nama_odp }}</flux:heading>
                <flux:badge size="sm" color="sky">{{ $odp->kapasitas_port }} Port</flux:badge>
            </div>
            <flux:subheading>
                {{ $odp->perumahan ? 'Wilayah '.$odp->perumahan->nama_perumahan.' • ' : '' }}
                Titik Infrastruktur Optical Distribution Point
            </flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button :href="route('maps.index')" wire:navigate variant="subtle" icon="map">
                Buka di Maps Lokasi
            </flux:button>
            @if (is_numeric($odp->latitude) && is_numeric($odp->longitude))
                <flux:button
                    :href="route('maps.estimasi-kabel', ['lat' => $odp->latitude, 'lng' => $odp->longitude])"
                    wire:navigate
                    variant="subtle"
                    icon="calculator"
                >
                    Estimasi Kabel
                </flux:button>
            @endif
            @can('update', $odp)
                <flux:button :href="route('odp.edit', $odp)" wire:navigate variant="primary" icon="pencil-square">
                    Edit ODP
                </flux:button>
            @endcan
            <flux:button :href="route('odp.index')" wire:navigate variant="ghost" icon="arrow-left">
                Kembali
            </flux:button>
        </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Info Card --}}
        <div class="space-y-4 lg:col-span-1">
            <flux:card class="p-6 space-y-4">
                <flux:heading size="base">Informasi Titik ODP</flux:heading>

                <div class="space-y-3 text-sm">
                    <div>
                        <span class="text-xs text-zinc-400">Nama ODP</span>
                        <div class="font-bold text-zinc-900 dark:text-zinc-100">{{ $odp->nama_odp }}</div>
                    </div>

                    <div>
                        <span class="text-xs text-zinc-400">Kapasitas Port</span>
                        <div class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $odp->kapasitas_port }} Port</div>
                    </div>

                    @if ($odp->perumahan)
                        <div>
                            <span class="text-xs text-zinc-400">Area / Perumahan</span>
                            <div class="font-medium text-zinc-800 dark:text-zinc-200">{{ $odp->perumahan->nama_perumahan }}</div>
                        </div>
                    @endif

                    <div>
                        <span class="text-xs text-zinc-400">Keterangan / Deskripsi PON</span>
                        <div class="text-zinc-600 dark:text-zinc-400 text-xs">{{ $odp->keterangan ?: 'Tidak ada keterangan tambahan.' }}</div>
                    </div>
                </div>

                <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 space-y-2">
                    <span class="text-xs font-semibold text-zinc-400 uppercase tracking-wider">Koordinat Geospasial</span>
                    @if (is_numeric($odp->latitude) && is_numeric($odp->longitude))
                        <div class="grid grid-cols-2 gap-2 font-mono text-xs">
                            <div class="p-2 rounded bg-zinc-50 dark:bg-zinc-800/60">
                                <span class="text-[10px] text-zinc-400 block">Latitude</span>
                                <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ number_format((float) $odp->latitude, 6) }}</span>
                            </div>
                            <div class="p-2 rounded bg-zinc-50 dark:bg-zinc-800/60">
                                <span class="text-[10px] text-zinc-400 block">Longitude</span>
                                <span class="font-semibold text-zinc-700 dark:text-zinc-300">{{ number_format((float) $odp->longitude, 6) }}</span>
                            </div>
                        </div>
                    @else
                        <div class="text-xs text-zinc-400 italic">Koordinat belum diatur.</div>
                    @endif
                </div>
            </flux:card>
        </div>

        {{-- Map Preview --}}
        <div class="lg:col-span-2 space-y-4">
            <flux:card class="p-0 overflow-hidden relative">
                <div class="p-4 border-b border-zinc-100 dark:border-zinc-800 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <flux:icon name="map-pin" class="size-4 text-emerald-500" />
                        <flux:heading size="base">Visualisasi Peta & Radius Cakupan</flux:heading>
                    </div>
                    <span class="text-xs text-zinc-400 font-mono">Radius Estimasi: &plusmn; 250m</span>
                </div>

                @if (is_numeric($odp->latitude) && is_numeric($odp->longitude))
                    <div
                        wire:ignore
                        x-data="{
                            init() {
                                if (typeof L === 'undefined') return;
                                const lat = {{ (float) $odp->latitude }};
                                const lng = {{ (float) $odp->longitude }};
                                const map = L.map(this.$refs.odpMapContainer).setView([lat, lng], 16);

                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    maxZoom: 19,
                                    attribution: '&copy; OpenStreetMap'
                                }).addTo(map);

                                // Marker ODP
                                const odpIcon = L.divIcon({
                                    html: '<div style=\'background-color: #0284c7; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid #ffffff; box-shadow: 0 3px 8px rgba(0,0,0,0.4);\'><div style=\'width: 10px; height: 10px; border-radius: 50%; background-color: #ffffff;\'></div></div>',
                                    className: 'odp-pin',
                                    iconSize: [32, 32],
                                    iconAnchor: [16, 16],
                                });

                                const marker = L.marker([lat, lng], { icon: odpIcon }).addTo(map);
                                marker.bindPopup('<strong>{{ $odp->nama_odp }}</strong><br>Kapasitas: {{ $odp->kapasitas_port }} Port').openPopup();

                                // Radius Lingkaran Cakupan (250m)
                                L.circle([lat, lng], {
                                    radius: 250,
                                    color: '#0284c7',
                                    fillColor: '#38bdf8',
                                    fillOpacity: 0.15,
                                    weight: 1.5,
                                    dashArray: '4, 4'
                                }).addTo(map);

                                setTimeout(() => map.invalidateSize(), 300);
                            }
                        }"
                    >
                        <div x-ref="odpMapContainer" style="height: 480px; z-index: 1;" class="w-full bg-zinc-100 dark:bg-zinc-800"></div>
                    </div>
                @else
                    <div class="p-12 text-center text-zinc-400">
                        <flux:icon name="map-pin" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                        <div class="mt-2 text-sm">Titik koordinat belum ditentukan pada ODP ini.</div>
                    </div>
                @endif
            </flux:card>
        </div>
    </div>
</div>
