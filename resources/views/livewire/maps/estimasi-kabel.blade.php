<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Kalkulator Estimasi Kabel Drop</flux:heading>
            <flux:subheading>Cari kandidat ODP terdekat dan perkirakan panjang tarikan kabel optik fisik sebelum survey lapangan.</flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:button :href="route('maps.index')" wire:navigate variant="subtle" icon="map">
                Buka Data Maps
            </flux:button>
        </div>
    </div>

    {{-- Parameter Form Card --}}
    <flux:card class="p-6">
        <form wire:submit="hitungEstimasi" class="space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Koordinat Input --}}
                <flux:field>
                    <flux:label>Latitude Titik Survey</flux:label>
                    <flux:input wire:model="lat" placeholder="-6.2088000" />
                    <flux:error name="lat" />
                </flux:field>

                <flux:field>
                    <flux:label>Longitude Titik Survey</flux:label>
                    <flux:input wire:model="lng" placeholder="106.8456000" />
                    <flux:error name="lng" />
                </flux:field>

                <flux:field>
                    <flux:label>Filter Nama / PON <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
                    <flux:input wire:model="search" placeholder="Contoh: PON-1 atau ODP-MLT" />
                </flux:field>

                <flux:field>
                    <flux:label>Jumlah ODP Ditampilkan</flux:label>
                    <flux:select wire:model="limit">
                        <flux:select.option value="3">3 ODP Terdekat</flux:select.option>
                        <flux:select.option value="5">5 ODP Terdekat (Rekomendasi)</flux:select.option>
                        <flux:select.option value="10">10 ODP Terdekat</flux:select.option>
                        <flux:select.option value="15">15 ODP Terdekat</flux:select.option>
                    </flux:select>
                </flux:field>
            </div>

            {{-- Parameter Faktor & Reserve --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3 pt-2 border-t border-zinc-100 dark:border-zinc-800">
                <flux:field>
                    <flux:label>Faktor Pengali Rute Fisik</flux:label>
                    <flux:input wire:model="faktor" type="number" step="0.1" min="1.0" max="3.0" />
                    <flux:description>Rasio kompensasi belokan jalan & tiang (default: 1.3).</flux:description>
                    <flux:error name="faktor" />
                </flux:field>

                <flux:field>
                    <flux:label>Cadangan Kabel (Slack Reserve)</flux:label>
                    <flux:input wire:model="reserve" type="number" min="0" max="500" />
                    <flux:description>Tambahan meter untuk splicing & penurunan (default: 25m).</flux:description>
                    <flux:error name="reserve" />
                </flux:field>

                <div class="flex items-end gap-2">
                    <flux:button
                        type="submit"
                        variant="primary"
                        icon="calculator"
                        class="w-full"
                        wire:loading.attr="disabled"
                    >
                        <span wire:loading.remove wire:target="hitungEstimasi">Hitung Estimasi Kabel</span>
                        <span wire:loading wire:target="hitungEstimasi">Mengkalkulasi...</span>
                    </flux:button>
                </div>
            </div>
        </form>
    </flux:card>

    {{-- Results & Map Container --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
        {{-- Kolom Kiri: Tabel / Daftar ODP Kandidat --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="base">Hasil Rekomendasi ODP Terdekat</flux:heading>
                @if (!empty($results))
                    <flux:badge size="sm" color="sky">{{ count($results) }} ODP</flux:badge>
                @endif
            </div>

            @if (empty($results))
                <flux:card class="p-8 text-center border-dashed">
                    <flux:icon name="calculator" class="mx-auto size-8 text-zinc-400" />
                    <div class="mt-2 text-sm font-medium text-zinc-700 dark:text-zinc-300">Belum ada hasil kalkulasi</div>
                    <div class="mt-1 text-xs text-zinc-400">Tentukan koordinat survey dan klik "Hitung Estimasi Kabel" untuk memulai.</div>
                </flux:card>
            @else
                <div class="space-y-3">
                    @foreach ($results as $index => $r)
                        @php
                            $isSelected = $selectedOdpId === $r['id'];
                            $isTopPick = $index === 0;
                        @endphp

                        <div
                            wire:click="selectOdp({{ $r['id'] }})"
                            class="cursor-pointer rounded-xl border p-4 transition-all {{ $isSelected ? 'border-primary-500 bg-primary-50/40 shadow-sm dark:border-primary-600 dark:bg-primary-950/20 ring-1 ring-primary-500' : 'border-zinc-200 bg-white hover:border-zinc-300 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-zinc-600' }}"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="flex size-5 items-center justify-center rounded-full bg-zinc-200 text-[11px] font-bold text-zinc-700 dark:bg-zinc-700 dark:text-zinc-200">
                                            {{ $index + 1 }}
                                        </span>
                                        <span class="font-bold text-zinc-900 dark:text-zinc-100 text-sm">
                                            {{ $r['nama_odp'] }}
                                        </span>
                                        @if ($isTopPick)
                                            <flux:badge size="sm" color="emerald">Paling Dekat</flux:badge>
                                        @endif
                                    </div>
                                    <div class="text-xs text-zinc-500 mt-1">
                                        {{ $r['perumahan'] ? $r['perumahan'].' • ' : '' }}
                                        {{ $r['keterangan'] ?: 'ODP '.$r['total_port'].' Port' }}
                                    </div>
                                </div>

                                <div>
                                    @if ($r['port_kosong'] > 0)
                                        <flux:badge size="sm" color="emerald">{{ $r['port_kosong'] }} Port Kosong</flux:badge>
                                    @else
                                        <flux:badge size="sm" color="rose">Penuh</flux:badge>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-3 pt-3 border-t border-zinc-100 dark:border-zinc-800 grid grid-cols-2 gap-2 text-xs">
                                <div>
                                    <span class="text-zinc-400 text-[11px]">Jarak Lurus Udara</span>
                                    <div class="font-mono font-semibold text-zinc-700 dark:text-zinc-300">{{ number_format($r['jarak_lurus']) }} meter</div>
                                </div>
                                <div class="text-right">
                                    <span class="text-primary-600 dark:text-primary-400 font-semibold text-[11px]">Estimasi Panjang Kabel</span>
                                    <div class="font-mono font-bold text-primary-600 dark:text-primary-400 text-sm">
                                        &plusmn; {{ number_format($r['estimasi_kabel']) }} meter
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Kolom Kanan: Peta Interaktif Survey & Garis Rute --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="base">Visualisasi Peta Geospasial</flux:heading>
                <span class="text-xs text-zinc-400">Klik peta untuk memindahkan titik survey</span>
            </div>

            <flux:card class="p-0 overflow-hidden relative">
                <div
                    wire:ignore
                    x-data="estimasiKabelView()"
                    class="relative"
                >
                    <div
                        x-ref="mapContainer"
                        style="height: 520px; z-index: 1;"
                        class="w-full bg-zinc-100 dark:bg-zinc-800"
                    ></div>

                    {{-- Tombol Gunakan Lokasi Saya --}}
                    <div class="absolute top-3 right-3 z-[500]">
                        <flux:button
                            type="button"
                            size="xs"
                            variant="subtle"
                            icon="map-pin"
                            x-on:click="useCurrentLocation()"
                            class="shadow-md bg-white/90 dark:bg-zinc-800/90 backdrop-blur"
                        >
                            Gunakan Lokasi Saya (GPS)
                        </flux:button>
                    </div>
                </div>
            </flux:card>
        </div>
    </div>
</div>

<script>
function estimasiKabelView() {
    return {
        map: null,
        surveyMarker: null,
        odpMarkers: [],
        polylines: [],

        init() {
            if (typeof L === 'undefined') return;

            this.$nextTick(() => {
                this.initMap();
            });

            this.$watch('$wire.lat', () => this.updateSurveyMarker());
            this.$watch('$wire.lng', () => this.updateSurveyMarker());
            this.$watch('$wire.results', () => this.renderOdpResults());
            this.$watch('$wire.selectedOdpId', (id) => this.highlightSelectedOdp(id));
        },

        initMap() {
            const initialLat = this.$wire.lat ? parseFloat(this.$wire.lat) : -6.2088;
            const initialLng = this.$wire.lng ? parseFloat(this.$wire.lng) : 106.8456;

            this.map = L.map(this.$refs.mapContainer).setView([initialLat, initialLng], 15);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
            }).addTo(this.map);

            this.map.on('click', (e) => {
                this.$wire.lat = parseFloat(e.latlng.lat.toFixed(7));
                this.$wire.lng = parseFloat(e.latlng.lng.toFixed(7));
                this.updateSurveyMarker();
            });

            this.updateSurveyMarker();
            this.renderOdpResults();

            setTimeout(() => {
                this.map.invalidateSize();
            }, 300);
        },

        updateSurveyMarker() {
            const lat = this.$wire.lat;
            const lng = this.$wire.lng;
            if (!lat || !lng) return;

            const sLat = parseFloat(lat);
            const sLng = parseFloat(lng);

            if (this.surveyMarker) {
                this.surveyMarker.setLatLng([sLat, sLng]);
            } else {
                const surveyIcon = L.divIcon({
                    html: '<div style="background-color: #ef4444; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 3px solid #ffffff; box-shadow: 0 3px 8px rgba(0,0,0,0.4);"><div style="width: 10px; height: 10px; border-radius: 50%; background-color: #ffffff;"></div></div>',
                    className: 'survey-pin',
                    iconSize: [32, 32],
                    iconAnchor: [16, 16],
                });
                this.surveyMarker = L.marker([sLat, sLng], { icon: surveyIcon, draggable: true }).addTo(this.map);
                this.surveyMarker.bindPopup('<strong>Titik Survey Calon Pelanggan</strong>');
                this.surveyMarker.on('dragend', (e) => {
                    const pos = e.target.getLatLng();
                    this.$wire.lat = parseFloat(pos.lat.toFixed(7));
                    this.$wire.lng = parseFloat(pos.lng.toFixed(7));
                });
            }
        },

        renderOdpResults() {
            this.odpMarkers.forEach(m => this.map.removeLayer(m));
            this.polylines.forEach(p => this.map.removeLayer(p));
            this.odpMarkers = [];
            this.polylines = [];

            const results = this.$wire.results || [];
            if (results.length === 0) return;

            const bounds = [];
            const lat = this.$wire.lat;
            const lng = this.$wire.lng;

            if (lat && lng) {
                bounds.push([parseFloat(lat), parseFloat(lng)]);
            }

            results.forEach((r, idx) => {
                const odpLat = parseFloat(r.lat);
                const odpLng = parseFloat(r.lng);
                bounds.push([odpLat, odpLng]);

                const odpIcon = L.divIcon({
                    html: '<div style="background-color: #0284c7; width: 26px; height: 26px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid #ffffff; box-shadow: 0 2px 6px rgba(0,0,0,0.3); color: white; font-weight: bold; font-size: 11px;">' + (idx + 1) + '</div>',
                    className: 'odp-pin',
                    iconSize: [26, 26],
                    iconAnchor: [13, 13],
                });

                const marker = L.marker([odpLat, odpLng], { icon: odpIcon }).addTo(this.map);
                marker.bindPopup('<strong>' + r.nama_odp + '</strong><br>Estimasi Kabel: <strong>&plusmn; ' + r.estimasi_kabel + 'm</strong><br>Sisa Port: ' + r.port_kosong + '/' + r.total_port);
                this.odpMarkers.push(marker);

                if (lat && lng) {
                    const poly = L.polyline([
                        [parseFloat(lat), parseFloat(lng)],
                        [odpLat, odpLng]
                    ], {
                        color: idx === 0 ? '#10b981' : '#94a3b8',
                        weight: idx === 0 ? 3 : 2,
                        dashArray: '6, 6',
                        opacity: 0.8
                    }).addTo(this.map);
                    poly.bindTooltip(r.nama_odp + ': &plusmn;' + r.estimasi_kabel + 'm', { sticky: true });
                    this.polylines.push(poly);
                }
            });

            if (bounds.length > 0) {
                this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16 });
            }
        },

        highlightSelectedOdp(id) {
            const results = this.$wire.results || [];
            const found = results.find(r => r.id === id);
            if (found) {
                this.map.panTo([parseFloat(found.lat), parseFloat(found.lng)]);
            }
        },

        useCurrentLocation() {
            if (!navigator.geolocation) {
                alert('Geolokasi tidak didukung.');
                return;
            }
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    this.$wire.lat = parseFloat(pos.coords.latitude.toFixed(7));
                    this.$wire.lng = parseFloat(pos.coords.longitude.toFixed(7));
                    this.updateSurveyMarker();
                    this.map.setView([this.$wire.lat, this.$wire.lng], 16);
                },
                (err) => alert('Gagal membaca GPS: ' + err.message),
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }
    };
}
</script>
