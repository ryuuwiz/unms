{{-- Hasil tombol cek status pembayaran tingkat invoice untuk staf (CekStatusPembayaranInvoice). --}}
@props(['hasil'])

<flux:callout :color="$hasil['warna']" icon="magnifying-glass-circle" {{ $attributes }}>
    <flux:callout.heading>Hasil cek status pembayaran Xendit: {{ $hasil['judul'] }}</flux:callout.heading>
    @if($hasil['keterangan'])
        <flux:callout.text>{{ $hasil['keterangan'] }}</flux:callout.text>
    @endif
</flux:callout>
