<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->no_invoice }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header table {
            width: 100%;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
        }
        .invoice-title {
            font-size: 26px;
            font-weight: bold;
            text-align: right;
            color: #1e3a8a;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 25px;
        }
        .meta-table td {
            vertical-align: top;
            width: 50%;
        }
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
        }
        .card-title {
            font-weight: bold;
            color: #475569;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #2563eb;
            color: #ffffff;
            text-align: left;
            padding: 8px 10px;
            font-size: 12px;
        }
        .items-table td {
            padding: 10px;
            border-bottom: 1px solid #e2e8f0;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .total-table {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
        }
        .total-table td {
            padding: 6px 10px;
        }
        .grand-total {
            background-color: #eff6ff;
            border-top: 2px solid #2563eb;
            font-size: 15px;
            font-weight: bold;
            color: #1e40af;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 11px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .badge-lunas { background-color: #dcfce7; color: #166534; }
        .badge-menunggu { background-color: #fef3c7; color: #92400e; }
        .badge-kadaluarsa { background-color: #ffe4e6; color: #9f1239; }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 11px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="company-name">UNMS ISP Network</div>
                    <div>Layanan Internet Cepat & Manajemen Jaringan</div>
                    <div>Email: support@isp-unms.local | WA: 0812-3456-7890</div>
                </td>
                <td style="text-align: right;">
                    <div class="invoice-title">TAGIHAN</div>
                    <div style="font-weight: bold; font-size: 14px;">{{ $invoice->no_invoice }}</div>
                    <div style="margin-top: 5px;">
                        @if($invoice->status->value === 'lunas')
                            <span class="badge badge-lunas">LUNAS</span>
                        @elseif($invoice->status->value === 'menunggu_pembayaran')
                            <span class="badge badge-menunggu">MENUNGGU PEMBAYARAN</span>
                        @else
                            <span class="badge badge-kadaluarsa">{{ strtoupper($invoice->status->label()) }}</span>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta-table">
        <tr>
            <td>
                <div class="card" style="margin-right: 10px;">
                    <div class="card-title">Ditagihkan Kepada:</div>
                    <strong>{{ $invoice->pelanggan->nama_lengkap }}</strong><br>
                    No. Reg: {{ $invoice->pelanggan->no_reg }}<br>
                    No. HP: {{ $invoice->pelanggan->no_hp }}<br>
                    Alamat: {{ $invoice->pelanggan->alamat_lengkap }}
                </div>
            </td>
            <td>
                <div class="card" style="margin-left: 10px;">
                    <div class="card-title">Informasi Tagihan & Layanan:</div>
                    Site ID: <strong>{{ $invoice->layananPelanggan->site_id }}</strong><br>
                    Paket: {{ $invoice->layananPelanggan->paketLayanan->nama_paket }}<br>
                    Tanggal Terbit: {{ $invoice->tanggal_terbit->format('d/m/Y') }}<br>
                    Jatuh Tempo: <strong>{{ $invoice->tanggal_jatuh_tempo->format('d/m/Y') }}</strong>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Deskripsi Layanan</th>
                <th>Masa Aktif</th>
                <th style="text-align: right;">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>
                    <strong>Langganan Internet - {{ $invoice->layananPelanggan->paketLayanan->nama_paket }}</strong><br>
                    <small style="color: #64748b;">Username PPP: {{ $invoice->layananPelanggan->ppp_username }} | Router: {{ $invoice->layananPelanggan->router->nama_router }}</small>
                </td>
                <td>{{ $invoice->layananPelanggan->paketLayanan->masa_aktif_nilai }} {{ ucfirst($invoice->layananPelanggan->paketLayanan->masa_aktif_satuan->value) }}</td>
                <td style="text-align: right;">{{ number_format((float) $invoice->jumlah, 0, ',', '.') }}</td>
            </tr>
            @if($invoice->promo)
            <tr>
                <td>2</td>
                <td>
                    <strong>Potongan Promo ({{ $invoice->promo->kode_promo }})</strong><br>
                    <small style="color: #64748b;">{{ $invoice->promo->nama_promo }}</small>
                </td>
                <td>-</td>
                <td style="text-align: right; color: #dc2626;">-{{ number_format((float) ($invoice->jumlah - $invoice->jumlah_setelah_promo), 0, ',', '.') }}</td>
            </tr>
            @endif
        </tbody>
    </table>

    <table class="total-table">
        <tr>
            <td>Subtotal:</td>
            <td style="text-align: right;">Rp {{ number_format((float) $invoice->jumlah, 0, ',', '.') }}</td>
        </tr>
        @if($invoice->promo)
        <tr>
            <td>Diskon:</td>
            <td style="text-align: right; color: #dc2626;">-Rp {{ number_format((float) ($invoice->jumlah - $invoice->jumlah_setelah_promo), 0, ',', '.') }}</td>
        </tr>
        @endif
        <tr class="grand-total">
            <td>TOTAL:</td>
            <td style="text-align: right;">Rp {{ number_format((float) $invoice->jumlah_setelah_promo, 0, ',', '.') }}</td>
        </tr>
    </table>

    @if($invoice->pembayarans->isNotEmpty())
    <div style="margin-top: 25px;">
        <div style="font-weight: bold; margin-bottom: 5px; color: #166534;">Riwayat Pembayaran Diterima:</div>
        <table style="width: 100%; border-collapse: collapse; font-size: 12px;">
            <thead>
                <tr style="background-color: #f1f5f9;">
                    <th style="padding: 5px; text-align: left;">Tanggal Bayar</th>
                    <th style="padding: 5px; text-align: left;">Metode</th>
                    <th style="padding: 5px; text-align: left;">No. Referensi</th>
                    <th style="padding: 5px; text-align: right;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->pembayarans as $bayar)
                <tr>
                    <td style="padding: 5px; border-bottom: 1px solid #e2e8f0;">{{ $bayar->dibayar_pada->format('d/m/Y H:i') }}</td>
                    <td style="padding: 5px; border-bottom: 1px solid #e2e8f0;">{{ $bayar->metode->label() }}</td>
                    <td style="padding: 5px; border-bottom: 1px solid #e2e8f0;">{{ $bayar->referensi_transaksi ?: '-' }}</td>
                    <td style="padding: 5px; border-bottom: 1px solid #e2e8f0; text-align: right;">Rp {{ number_format((float) $bayar->jumlah_dibayar, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        Harap simpan invoice ini sebagai bukti tagihan resmi. Terima kasih atas kepercayaan Anda menggunakan layanan kami.
    </div>
</body>
</html>
