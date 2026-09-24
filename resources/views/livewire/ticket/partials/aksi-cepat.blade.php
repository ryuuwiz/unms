@php
    $telepon = preg_replace('/[^0-9+]/', '', (string) ($tck->pelanggan?->no_hp ?? ''));
    $adaKoordinat = $tck->pelanggan && is_numeric($tck->pelanggan->latitude) && is_numeric($tck->pelanggan->longitude);
    $ukuran = $ukuran ?? 'base';
@endphp
<div class="flex items-center gap-1.5">
    @if ($telepon !== '')
        <flux:button :href="'tel:'.$telepon" :size="$ukuran" variant="subtle" icon="phone" aria-label="Telepon {{ $tck->pelanggan?->namaLengkap() }}" title="Telepon pelanggan" />
    @endif
    @if ($adaKoordinat)
        <flux:button :href="'https://www.google.com/maps/dir/?api=1&destination='.$tck->pelanggan->latitude.','.$tck->pelanggan->longitude" target="_blank" rel="noopener noreferrer" :size="$ukuran" variant="subtle" icon="map-pin" aria-label="Buka lokasi di Maps" title="Buka lokasi di Maps" />
    @endif
    <flux:button :href="route('ticket.show', $tck)" wire:navigate :size="$ukuran" variant="subtle" icon="eye" aria-label="Lihat detail {{ $tck->nomor_ticket }}" title="Lihat detail tiket" />
</div>
