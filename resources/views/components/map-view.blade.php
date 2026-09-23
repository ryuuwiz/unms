@props([
    'lat' => null,
    'lng' => null,
    'zoom' => 15,
    'height' => '240px',
    'popupTitle' => null,
    'popupSubtitle' => null,
    'marker' => true,
    'interactive' => true,
])

@php
    $latitude = is_numeric($lat) ? (float) $lat : null;
    $longitude = is_numeric($lng) ? (float) $lng : null;
@endphp

@if ($latitude !== null && $longitude !== null)
    <div
        wire:ignore
        x-data="{
            map: null,
            markerInstance: null,
            lat: {{ $latitude }},
            lng: {{ $longitude }},
            zoom: {{ $zoom }},
            interactive: {{ $interactive ? 'true' : 'false' }},
            showMarker: {{ $marker ? 'true' : 'false' }},
            popupTitle: {{ Js::from($popupTitle) }},
            popupSubtitle: {{ Js::from($popupSubtitle) }},

            init() {
                if (typeof L === 'undefined') {
                    console.warn('Leaflet.js is not loaded.');
                    return;
                }

                this.$nextTick(() => {
                    this.initMap();
                });
            },

            initMap() {
                if (this.map) {
                    this.map.remove();
                }

                this.map = L.map(this.$refs.mapContainer, {
                    zoomControl: this.interactive,
                    dragging: this.interactive,
                    touchZoom: this.interactive,
                    scrollWheelZoom: false,
                    doubleClickZoom: this.interactive,
                    boxZoom: this.interactive,
                    keyboard: this.interactive,
                    attributionControl: true
                }).setView([this.lat, this.lng], this.zoom);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; <a href=\'https://www.openstreetmap.org/copyright\' target=\'_blank\' rel=\'noopener noreferrer\'>OpenStreetMap</a>'
                }).addTo(this.map);

                if (this.showMarker) {
                    this.markerInstance = L.marker([this.lat, this.lng]).addTo(this.map);

                    if (this.popupTitle || this.popupSubtitle) {
                        let popupHtml = '<div class=\'text-xs font-sans space-y-1 p-0.5 min-w-[140px]\'>';
                        if (this.popupTitle) {
                            popupHtml += `<div class=\'font-semibold text-zinc-900\'>${this.popupTitle}</div>`;
                        }
                        if (this.popupSubtitle) {
                            popupHtml += `<div class=\'text-zinc-600 text-[11px] leading-snug\'>${this.popupSubtitle}</div>`;
                        }
                        popupHtml += `<div class=\'text-[10px] text-zinc-400 font-mono pt-1 border-t border-zinc-100\'>${this.lat.toFixed(6)}, ${this.lng.toFixed(6)}</div>`;
                        popupHtml += '</div>';
                        this.markerInstance.bindPopup(popupHtml);
                    }
                }

                new ResizeObserver(() => this.map?.invalidateSize()).observe(this.$refs.mapContainer);
            },

            recenter() {
                if (this.map) {
                    this.map.setView([this.lat, this.lng], this.zoom);
                    if (this.markerInstance) {
                        this.markerInstance.openPopup();
                    }
                }
            }
        }"
        class="relative isolate overflow-hidden rounded-lg border border-zinc-200 shadow-sm dark:border-zinc-700"
    >
        <div
            x-ref="mapContainer"
            style="height: {{ $height }}; z-index: 1;"
            class="w-full bg-zinc-100 dark:bg-zinc-800"
        ></div>

        {{-- Quick Recenter Button --}}
        <button
            type="button"
            x-on:click="recenter()"
            title="Pusatkan ke Titik Lokasi"
            class="absolute right-2 top-2 z-[400] flex size-7 items-center justify-center rounded-md border border-zinc-200 bg-white/90 text-zinc-700 shadow-sm backdrop-blur transition hover:bg-white dark:border-zinc-700 dark:bg-zinc-800/90 dark:text-zinc-200 dark:hover:bg-zinc-800"
        >
            <flux:icon name="viewfinder-circle" class="size-4" />
        </button>
    </div>
@endif
