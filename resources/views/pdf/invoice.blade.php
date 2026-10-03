<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->no_invoice }} - {{ $namaBrand }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; color: #000; font-size: 11px; line-height: 1.45; margin: 0; padding: 20px 24px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; padding: 0; }
        .muted { color: #555; }
        .rule { border-top: 1px solid #000; margin: 12px 0; }
        .logo { max-height: 44px; max-width: 140px; margin-right: 8px; }
        .brand { font-size: 18px; font-weight: bold; letter-spacing: 0.5px; }
        .title { font-size: 20px; font-weight: bold; letter-spacing: 2px; }
        .label { font-weight: bold; margin-bottom: 2px; }
        .items th { border-bottom: 1px solid #000; padding: 5px 4px; text-align: left; font-size: 10px; text-transform: uppercase; }
        .items td { border-bottom: 1px solid #ddd; padding: 5px 4px; }
        .right, .items th.right { text-align: right; }
        .kv td { padding: 2px 0; }
        .grand td { font-weight: bold; font-size: 12px; border-top: 1px solid #000; padding-top: 4px; }
        .footer { font-size: 9px; color: #555; }
    </style>
</head>
<body>
    @php
        $rp = fn ($nilai) => 'Rp. '.number_format((float) $nilai, 0, ',', '.');
        $tanggal = fn ($t) => $t?->translatedFormat('d M Y');
    @endphp

    <table>
        <tr>
            <td style="width: 60%;">
                <table>
                    <tr>
                        @if ($logoBase64)
                            <td style="width: 1%;"><img src="{{ $logoBase64 }}" class="logo" alt="{{ $namaBrand }}"></td>
                        @endif
                        <td>
                            <div class="brand">{{ $namaBrand }}</div>
                            @if ($perusahaan->alamat)<div>{{ $perusahaan->alamat }}@if ($perusahaan->kota), {{ $perusahaan->kota }}@endif</div>@endif
                            @if ($perusahaan->telepon)<div>Telp: {{ $perusahaan->telepon }}</div>@endif
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 40%;" class="right">
                <div class="title">INVOICE</div>
                <div>Invoice # {{ $invoice->no_invoice }}</div>
                <div>Tanggal Invoice: {{ $tanggal($invoice->tanggal_terbit) }}</div>
                <div>Status: <strong>{{ $invoice->status->label() }}</strong></div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table>
        <tr>
            <td style="width: 45%; padding-right: 12px;">
                <div class="label">Pelanggan</div>
                <div>{{ $invoice->pelanggan->namaLengkap() }}</div>
                <div>No. Registrasi: {{ $invoice->pelanggan->no_reg }}</div>
            </td>
            <td style="width: 55%;">
                <div class="label">Detail Layanan</div>
                <div>Layanan / Site ID: {{ $invoice->layananPelanggan->site_id ?? '-' }}</div>
                <div>Keterangan: {{ $cetak->keterangan }}</div>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th>Deskripsi</th>
                <th class="right" style="width: 16%;">Harga</th>
                <th class="right" style="width: 7%;">Qty</th>
                <th class="right" style="width: 16%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($cetak->baris as $i => $baris)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $baris['deskripsi'] }}</td>
                    <td class="right">{{ $rp($baris['harga']) }}</td>
                    <td class="right">{{ $baris['qty'] }}</td>
                    <td class="right">{{ $rp($baris['harga'] * $baris['qty']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="rule"></div>

    <table>
        <tr>
            <td style="width: 55%; padding-right: 12px;">
                <div class="label">Informasi Pembayaran</div>
                <table class="kv">
                    @if ($cetak->pembayaran)
                        <tr><td style="width: 35%;">Tanggal Bayar</td><td>{{ $cetak->pembayaran->dibayar_pada->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB</td></tr>
                        <tr><td>Metode</td><td>{{ $cetak->metode }}</td></tr>
                        <tr><td>Sales / Teller</td><td>{{ $cetak->teller }}</td></tr>
                    @else
                        <tr><td style="width: 35%;">Jatuh Tempo</td><td>{{ $tanggal($invoice->tanggal_jatuh_tempo) }}</td></tr>
                    @endif
                </table>
                @if ($qrTagihan)
                    <table style="margin-top: 8px;">
                        <tr>
                            <td style="width: 76px;"><img src="{{ $qrTagihan }}" width="70" height="70" alt="QR Tagihan"></td>
                            <td class="footer">Pindai untuk membuka tagihan ini dan membayar online.</td>
                        </tr>
                    </table>
                @endif
            </td>
            <td style="width: 45%;">
                <table class="kv">
                    <tr><td>Subtotal</td><td class="right">{{ $rp($cetak->subtotal) }}</td></tr>
                    <tr><td>Diskon Promo</td><td class="right">{{ $rp($cetak->diskon) }}</td></tr>
                    <tr class="grand"><td>Total Bayar</td><td class="right">{{ $rp($cetak->total) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="rule"></div>

    <div class="footer">Dicetak pada: {{ now('Asia/Jakarta')->translatedFormat('d M Y H:i') }} WIB. Invoice #{{ $invoice->no_invoice }}</div>
</body>
</html>
