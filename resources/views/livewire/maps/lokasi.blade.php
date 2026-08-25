<div class="space-y-4">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Maps Lokasi & Sebaran Jaringan</flux:heading>
            <flux:subheading>Peta geospasial sebaran infrastruktur ODP, pelanggan aktif, layanan site, dan area cakupan perumahan.</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button :href="route('maps.estimasi-kabel')" wire:navigate variant="subtle" icon="calculator">
                Kalkulator Estimasi Kabel
            </flux:button>
            <flux:button :href="route('odp.create')" wire:navigate variant="primary" icon="plus">
                Tambah ODP
            </flux:button>
        </div>
    </div>

    {{-- Filter Toolbar & Layer Toggles --}}
    <flux:card class="p-4 space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-4">
            {{-- Layer Toggle Pills --}}
            <div class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-semibold text-zinc-500 uppercase tracking-wider">Layer Peta:</span>

                {{-- Pelanggan --}}
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border cursor-pointer transition-colors {{ $layerPelanggan ? 'bg-emerald-50 text-emerald-800 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-700' : 'bg-zinc-50 text-zinc-500 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700' }}">
                    <input type="checkbox" wire:model.live="layerPelanggan" class="sr-only">
                    <span class="size-2 rounded-full bg-emerald-500 inline-block"></span>
                    Pelanggan ({{ $totalPelanggan }})
                </label>

                {{-- ODP --}}
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border cursor-pointer transition-colors {{ $layerOdp ? 'bg-sky-50 text-sky-800 border-sky-300 dark:bg-sky-950/40 dark:text-sky-300 dark:border-sky-700' : 'bg-zinc-50 text-zinc-500 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700' }}">
                    <input type="checkbox" wire:model.live="layerOdp" class="sr-only">
                    <span class="size-2 rounded-full bg-sky-500 inline-block"></span>
                    ODP ({{ $totalOdp }})
                </label>

                {{-- Layanan / Site --}}
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border cursor-pointer transition-colors {{ $layerLayanan ? 'bg-amber-50 text-amber-800 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-700' : 'bg-zinc-50 text-zinc-500 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700' }}">
                    <input type="checkbox" wire:model.live="layerLayanan" class="sr-only">
                    <span class="size-2 rounded-full bg-amber-500 inline-block"></span>
                    Layanan (Site)
                </label>

                {{-- Perumahan Titik Pusat --}}
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border cursor-pointer transition-colors {{ $layerPerumahan ? 'bg-indigo-50 text-indigo-800 border-indigo-300 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-700' : 'bg-zinc-50 text-zinc-500 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700' }}">
                    <input type="checkbox" wire:model.live="layerPerumahan" class="sr-only">
                    <span class="size-2 rounded-full bg-indigo-500 inline-block"></span>
                    Cluster ({{ $totalPerumahan }})
                </label>

                {{-- Coverage Polygons --}}
                <label class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-medium border cursor-pointer transition-colors {{ $layerCoverage ? 'bg-purple-50 text-purple-800 border-purple-300 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-700' : 'bg-zinc-50 text-zinc-500 border-zinc-200 dark:bg-zinc-800 dark:text-zinc-400 dark:border-zinc-700' }}">
                    <input type="checkbox" wire:model.live="layerCoverage" class="sr-only">
                    <flux:icon name="map" class="size-3 text-purple-500" />
                    Coverage Polygon
                </label>
            </div>

            {{-- Filter Area & Status --}}
            <div class="flex items-center gap-2">
                <select
                    wire:model.live="statusFilter"
                    class="rounded-lg border border-zinc-200 bg-white px-2.5 py-1.5 text-xs text-zinc-700 shadow-sm focus:border-primary-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                >
                    <option value="semua">Semua Status Pelanggan</option>
                    @foreach ($statuses as $st)
                        <option value="{{ $st->value }}">{{ $st->label() }}</option>
                    @endforeach
                </select>

                <select
                    wire:model.live="perumahanFilter"
                    class="rounded-lg border border-zinc-200 bg-white px-2.5 py-1.5 text-xs text-zinc-700 shadow-sm focus:border-primary-500 focus:outline-none dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"
                >
                    <option value="">Semua Perumahan</option>
                    @foreach ($perumahans as $prm)
                        <option value="{{ $prm->id }}">{{ $prm->nama_perumahan }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </flux:card>

    {{-- Interactive Leaflet Container --}}
    <flux:card class="p-0 overflow-hidden relative">
        <div
            wire:ignore
            x-data="mapsLokasiView()"
            class="relative"
        >
            {{-- Map element --}}
            <div
                x-ref="mapContainer"
                style="height: 640px; z-index: 1;"
                class="w-full bg-zinc-100 dark:bg-zinc-800"
            ></div>

            {{-- Loading Indicator --}}
            <div
                x-show="loading"
                class="absolute top-4 right-4 z-[500] flex items-center gap-2 rounded-lg bg-white/90 px-3 py-1.5 text-xs font-semibold text-zinc-800 shadow-md backdrop-blur dark:bg-zinc-800/90 dark:text-zinc-200"
            >
                <flux:icon name="arrow-path" class="size-4 animate-spin text-primary-600" />
                <span>Memuat data peta...</span>
            </div>

            {{-- Counter Pill --}}
            <div
                class="absolute bottom-4 left-4 z-[500] rounded-lg bg-white/90 px-3 py-1 text-xs font-semibold text-zinc-700 shadow-md backdrop-blur dark:bg-zinc-800/90 dark:text-zinc-200 border border-zinc-200 dark:border-zinc-700"
            >
                <span x-text="totalRendered"></span> Titik Ditampilkan
            </div>
        </div>
    </flux:card>
</div>

<script>
function mapsLokasiView() {
    return {
        map: null,
        clusterGroup: null,
        polygonLayerGroup: null,
        loading: false,
        totalRendered: 0,

        init() {
            if (typeof L === 'undefined') {
                console.warn('Leaflet.js belum termuat.');
                return;
            }

            this.$nextTick(() => {
                this.initMap();
                this.fetchAndRenderMarkers();
            });

            // Watch Livewire properties
            this.$watch('$wire.layerPelanggan', () => this.fetchAndRenderMarkers());
            this.$watch('$wire.layerLayanan', () => this.fetchAndRenderMarkers());
            this.$watch('$wire.layerOdp', () => this.fetchAndRenderMarkers());
            this.$watch('$wire.layerPerumahan', () => this.fetchAndRenderMarkers());
            this.$watch('$wire.layerCoverage', () => this.fetchAndRenderMarkers());
            this.$watch('$wire.statusFilter', () => this.fetchAndRenderMarkers());
            this.$watch('$wire.perumahanFilter', () => this.fetchAndRenderMarkers());
        },

        initMap() {
            if (this.map) {
                this.map.remove();
            }

            this.map = L.map(this.$refs.mapContainer, {
                center: [-6.2088, 106.8456],
                zoom: 13,
                scrollWheelZoom: true,
                zoomControl: true,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(this.map);

            if (typeof L.markerClusterGroup !== 'undefined') {
                this.clusterGroup = L.markerClusterGroup({
                    chunkedLoading: true,
                    maxClusterRadius: 40,
                    spiderfyOnMaxZoom: true,
                    showCoverageOnHover: false,
                });
                this.map.addLayer(this.clusterGroup);
            }

            this.polygonLayerGroup = L.layerGroup().addTo(this.map);

            setTimeout(() => {
                this.map.invalidateSize();
            }, 300);
        },

        getActiveLayersParam() {
            const layers = [];
            if (this.$wire.layerPelanggan) layers.push('pelanggan');
            if (this.$wire.layerLayanan) layers.push('layanan');
            if (this.$wire.layerOdp) layers.push('odp');
            if (this.$wire.layerPerumahan) layers.push('perumahan');
            if (this.$wire.layerCoverage) layers.push('coverage');
            return layers.join(',');
        },

        async fetchAndRenderMarkers() {
            this.loading = true;
            const layersParam = this.getActiveLayersParam();
            const statusFilter = this.$wire.statusFilter || 'semua';
            const perumahanFilter = this.$wire.perumahanFilter || '';
            const url = `/api/maps/markers?layers=${layersParam}&status_pelanggan=${statusFilter}&perumahan_id=${perumahanFilter}`;

            try {
                const response = await fetch(url);
                const data = await response.json();

                if (this.clusterGroup) {
                    this.clusterGroup.clearLayers();
                }
                if (this.polygonLayerGroup) {
                    this.polygonLayerGroup.clearLayers();
                }

                const markers = data.markers || [];
                const polygons = data.polygons || [];
                this.totalRendered = markers.length;

                const bounds = [];

                // Render Markers
                markers.forEach(m => {
                    const marker = this.createCustomMarker(m);
                    if (this.clusterGroup) {
                        this.clusterGroup.addLayer(marker);
                    } else {
                        marker.addTo(this.map);
                    }
                    bounds.push([m.lat, m.lng]);
                });

                // Render Polygons
                polygons.forEach(poly => {
                    if (poly.geojson) {
                        const geoLayer = L.geoJSON(poly.geojson, {
                            style: {
                                color: '#6366f1',
                                weight: 2,
                                opacity: 0.8,
                                fillColor: '#818cf8',
                                fillOpacity: 0.15
                            }
                        });
                        geoLayer.bindPopup('<strong>' + poly.nama + '</strong><br><span class="text-xs text-zinc-500">Area Cakupan Coverage</span>');
                        this.polygonLayerGroup.addLayer(geoLayer);
                    }
                });

                // Fit bounds if markers exist
                if (bounds.length > 0) {
                    this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
                }
            } catch (err) {
                console.error('Gagal mengambil data marker:', err);
            } finally {
                this.loading = false;
            }
        },

        createCustomMarker(data) {
            const colorMap = {
                emerald: '#10b981',
                sky: '#0284c7',
                amber: '#f59e0b',
                rose: '#f43f5e',
                indigo: '#6366f1',
                zinc: '#71717a'
            };
            const colorHex = colorMap[data.color] || '#0284c7';

            const iconHtml = '<div style="background-color: ' + colorHex + '; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.35);"><div style="width: 8px; height: 8px; border-radius: 50%; background-color: #ffffff;"></div></div>';

            const customIcon = L.divIcon({
                html: iconHtml,
                className: 'custom-map-pin',
                iconSize: [28, 28],
                iconAnchor: [14, 14],
                popupAnchor: [0, -14],
            });

            const marker = L.marker([data.lat, data.lng], { icon: customIcon });

            let popupHtml = '<div style="min-width: 180px; font-family: inherit;" class="p-1 space-y-1 text-xs">';
            popupHtml += '<div class="font-bold text-zinc-900 leading-tight">' + data.title + '</div>';
            popupHtml += '<div class="text-zinc-600">' + data.subtitle + '</div>';
            popupHtml += '<div class="pt-1 flex items-center justify-between border-t border-zinc-100 mt-1">';
            popupHtml += '<span style="color: ' + colorHex + '; font-weight: 600;">' + data.badge + '</span>';
            if (data.detail_url) {
                popupHtml += '<a href="' + data.detail_url + '" class="text-primary-600 underline font-medium" target="_self">Lihat Detail &rarr;</a>';
            }
            popupHtml += '</div></div>';

            marker.bindPopup(popupHtml);
            return marker;
        }
    };
}
</script>
