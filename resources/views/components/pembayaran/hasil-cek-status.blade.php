{{-- Hasil tombol cek status pembayaran tingkat invoice untuk staf (CekStatusPembayaranInvoice). --}}
@props(['hasil'])

@php
    [$judul, $keterangan, $warna] = match (true) {
        $hasil['lunas'] => ['Lunas', 'Pembayaran ditemukan di Xendit dan tagihan sudah Lunas.', 'green'],
        $hasil['alasanDilaporkan'] !== null => ['Dilaporkan untuk tindakan manual', $hasil['alasanDilaporkan'], 'amber'],
        $hasil['galat'] !== null => ['Belum dibayar / sebagian gagal dicek', 'Sebagian link gagal dicek ke Xendit: '.$hasil['galat'], 'red'],
        default => ['Belum dibayar', 'Tidak ada link pembayaran invoice ini yang lunas di Xendit.', 'zinc'],
    };
@endphp

<flux:callout :color="$warna" icon="magnifying-glass-circle" {{ $attributes }}>
    <flux:callout.heading>Hasil cek status pembayaran Xendit: {{ $judul }}</flux:callout.heading>
    <flux:callout.text>{{ $keterangan }}</flux:callout.text>
</flux:callout>
