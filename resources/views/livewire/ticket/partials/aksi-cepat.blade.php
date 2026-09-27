@php
    $telepon = preg_replace('/[^0-9+]/', '', (string) ($tck->pelanggan?->no_hp ?? ''));
    $adaKoordinat = $tck->pelanggan && is_numeric($tck->pelanggan->latitude) && is_numeric($tck->pelanggan->longitude);
@endphp
@if ($telepon !== '' || $adaKoordinat)
    <div class="relative z-10 mt-3 flex items-center gap-1.5 border-t border-zinc-100 pt-3 dark:border-zinc-700">
        @if ($telepon !== '')
            <flux:button :href="'tel:'.$telepon" variant="subtle" icon="phone" aria-label="Telepon {{ $tck->pelanggan?->namaLengkap() }}" title="Telepon pelanggan" />
        @endif
        @if ($adaKoordinat)
            <flux:button :href="'https://www.google.com/maps/dir/?api=1&destination='.$tck->pelanggan->latitude.','.$tck->pelanggan->longitude" target="_blank" rel="noopener noreferrer" variant="subtle" icon="map-pin" aria-label="Buka lokasi di Maps" title="Buka lokasi di Maps" />
        @endif
    </div>
@endif
