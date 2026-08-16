@props([
    'lat' => null,
    'lng' => null,
    'readonly' => false,
    'height' => '320px',
])

<div
    wire:ignore
    x-data="{
        map: null,
        marker: null,
        lat: @entangle('lat').live,
        lng: @entangle('lng').live,
        readonly: {{ $readonly ? 'true' : 'false' }},
        defaultLat: -6.2088,
        defaultLng: 106.8456,

        init() {
            if (typeof L === 'undefined') {
                return;
            }

            const initialLat = this.lat ? parseFloat(this.lat) : this.defaultLat;
            const initialLng = this.lng ? parseFloat(this.lng) : this.defaultLng;
            const hasCoords = Boolean(this.lat && this.lng);

            this.map = L.map(this.$refs.mapContainer).setView([initialLat, initialLng], hasCoords ? 15 : 12);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href=\'https://www.openstreetmap.org/copyright\'>OpenStreetMap</a> kontributor'
            }).addTo(this.map);

            if (hasCoords) {
                this.setMarker(initialLat, initialLng);
            }

            if (!this.readonly) {
                this.map.on('click', (e) => {
                    this.updateCoordinates(e.latlng.lat, e.latlng.lng);
                });
            }

            // Sync from Livewire when lat/lng are updated manually
            this.$watch('lat', (newLat) => {
                if (newLat && this.lng) {
                    const parsedLat = parseFloat(newLat);
                    const parsedLng = parseFloat(this.lng);
                    if (!isNaN(parsedLat) && !isNaN(parsedLng)) {
                        this.setMarker(parsedLat, parsedLng);
                        this.map.panTo([parsedLat, parsedLng]);
                    }
                }
            });

            this.$watch('lng', (newLng) => {
                if (newLng && this.lat) {
                    const parsedLat = parseFloat(this.lat);
                    const parsedLng = parseFloat(newLng);
                    if (!isNaN(parsedLat) && !isNaN(parsedLng)) {
                        this.setMarker(parsedLat, parsedLng);
                        this.map.panTo([parsedLat, parsedLng]);
                    }
                }
            });

            // Invalidate size on load so Leaflet renders tiles properly
            setTimeout(() => {
                this.map.invalidateSize();
            }, 250);
        },

        setMarker(latitude, longitude) {
            if (this.marker) {
                this.marker.setLatLng([latitude, longitude]);
            } else {
                this.marker = L.marker([latitude, longitude], {
                    draggable: !this.readonly
                }).addTo(this.map);

                if (!this.readonly) {
                    this.marker.on('dragend', (event) => {
                        const position = event.target.getLatLng();
                        this.updateCoordinates(position.lat, position.lng);
                    });
                }
            }
        },

        updateCoordinates(latitude, longitude) {
            const formattedLat = parseFloat(latitude.toFixed(7));
            const formattedLng = parseFloat(longitude.toFixed(7));
            this.lat = formattedLat;
            this.lng = formattedLng;
            this.setMarker(formattedLat, formattedLng);
        },

        useCurrentLocation() {
            if (!navigator.geolocation) {
                alert('Geolokasi tidak didukung oleh browser Anda.');
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const curLat = position.coords.latitude;
                    const curLng = position.coords.longitude;
                    this.updateCoordinates(curLat, curLng);
                    this.map.setView([curLat, curLng], 16);
                },
                (error) => {
                    alert('Gagal mendapatkan lokasi GPS: ' + error.message);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }
    }"
    class="space-y-2"
>
    @if (!$readonly)
        <div class="flex items-center justify-between">
            <span class="text-xs text-zinc-500 dark:text-zinc-400">
                Klik peta atau geser pin marker untuk menentukan titik instalasi.
            </span>
            <flux:button
                type="button"
                size="xs"
                variant="subtle"
                icon="map-pin"
                x-on:click="useCurrentLocation()"
            >
                Gunakan Lokasi Saya
            </flux:button>
        </div>
    @endif

    <div
        x-ref="mapContainer"
        style="height: {{ $height }}; z-index: 1;"
        class="w-full rounded-lg border border-zinc-200 shadow-inner dark:border-zinc-700"
    ></div>
</div>
