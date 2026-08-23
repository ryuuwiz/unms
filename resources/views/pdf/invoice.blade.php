<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->no_invoice }} - {{ $perusahaan->nama_brand ?: config('app.name', 'GOBILLING') }}</title>
    @if ($perusahaan->logo_url)
        <link rel="icon" href="{{ $perusahaan->logo_url }}">
        <link rel="apple-touch-icon" href="{{ $perusahaan->logo_url }}">
    @else
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @endif
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333;
            font-size: 12px;
            line-height: 1.4;
            margin: 0;
            padding: 15px 20px;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 15px;
        }
        .header table {
            width: 100%;
            border-collapse: collapse;
        }
        .company-logo {
            max-height: 50px;
            max-width: 180px;
            object-fit: contain;
            margin-bottom: 5px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            line-height: 1.2;
        }
        .company-tagline {
            font-size: 11px;
            color: #475569;
            margin-bottom: 3px;
        }
        .company-details {
            font-size: 10.5px;
            color: #64748b;
            line-height: 1.3;
        }
        .invoice-title {
            font-size: 24px;
            font-weight: bold;
            text-align: right;
            color: #1e3a8a;
            letter-spacing: 1px;
        }
        .invoice-number {
            font-weight: bold;
            font-size: 13px;
            color: #334155;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 15px;
            border-collapse: collapse;
        }
        .meta-table td {
            vertical-align: top;
            width: 50%;
        }
        .card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            margin-bottom: 5px;
            font-size: 11.5px;
        }
        .card-title {
            font-weight: bold;
            color: #2563eb;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 2px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .items-table th {
            background-color: #2563eb;
            color: #ffffff;
            text-align: left;
            padding: 7px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 11.5px;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .total-table {
            width: 45%;
            margin-left: auto;
            border-collapse: collapse;
            font-size: 11.5px;
        }
        .total-table td {
            padding: 5px 8px;
        }
        .grand-total {
            background-color: #eff6ff;
            border-top: 2px solid #2563eb;
            border-bottom: 2px solid #2563eb;
            font-size: 13.5px;
            font-weight: bold;
            color: #1e40af;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-lunas { background-color: #dcfce7; color: #166534; }
        .badge-menunggu { background-color: #fef3c7; color: #92400e; }
        .badge-kadaluarsa { background-color: #ffe4e6; color: #9f1239; }
        
        .info-grid {
            width: 100%;
            margin-top: 15px;
            border-collapse: collapse;
        }
        .info-grid td {
            vertical-align: top;
        }
        .payment-box {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 10px 12px;
            font-size: 11px;
            color: #166534;
        }
        .signature-box {
            text-align: center;
            font-size: 11px;
            color: #334155;
            padding-top: 5px;
        }
        .signature-space {
            height: 45px;
        }
        .footer {
            margin-top: 20px;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
            font-size: 10px;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $company = $perusahaan ?? \App\Models\Perusahaan::default();
    @endphp

    <div class="header">
        <table>
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    @if($company->logo_base64)
                        <div><img src="{{ $company->logo_base64 }}" class="company-logo" alt="{{ $company->nama_brand }}" /></div>
                    @endif
                    <div class="company-name">{{ $company->nama_perusahaan }}</div>
                    @if($company->tagline)
                        <div class="company-tagline">{{ $company->tagline }}</div>
                    @endif
                    <div class="company-details">
                        @if($company->alamat){{ $company->alamat }}@endif
                        @if($company->kota), {{ $company->kota }}@endif
                        @if($company->kode_pos) {{ $company->kode_pos }}@endif
                        <br>
                        @if($company->telepon)Telp: {{ $company->telepon }} | @endif
                        @if($company->whatsapp)WA: {{ $company->whatsapp }} | @endif
                        @if($company->email)Email: {{ $company->email }}@endif
                        @if($company->npwp)<br>NPWP: {{ $company->npwp }}@endif
                    </div>
                </td>
                <td style="width: 40%; text-align: right; vertical-align: top;">
                    <div class="invoice-title">TAGIHAN</div>
                    <div class="invoice-number">{{ $invoice->no_invoice }}</div>
                    <div style="margin-top: 6px;">
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
            <td style="padding-right: 6px;">
                <div class="card">
                    <div class="card-title">Ditagihkan Kepada:</div>
                    <strong>{{ $invoice->pelanggan->nama_lengkap }}</strong><br>
                    No. Reg: {{ $invoice->pelanggan->no_reg }}<br>
                    No. HP: {{ $invoice->pelanggan->no_hp }}<br>
                    Alamat: {{ $invoice->pelanggan->alamat_lengkap }}
                </div>
            </td>
            <td style="padding-left: 6px;">
                <div class="card">
                    <div class="card-title">Informasi Tagihan & Layanan:</div>
                    Site ID: <strong>{{ $invoice->layananPelanggan->site_id }}</strong><br>
                    Paket: {{ $invoice->layananPelanggan->paketLayanan->nama_paket }}<br>
                    Tanggal Terbit: {{ $invoice->tanggal_terbit->format('d/m/Y') }}<br>
                    Jatuh Tempo: <strong style="color: #dc2626;">{{ $invoice->tanggal_jatuh_tempo->format('d/m/Y') }}</strong>
                </div>
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 55%;">Deskripsi Layanan</th>
                <th style="width: 20%;">Masa Aktif</th>
                <th style="width: 20%; text-align: right;">Jumlah (Rp)</th>
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
            <td>TOTAL TAGIHAN:</td>
            <td style="text-align: right;">Rp {{ number_format((float) $invoice->jumlah_setelah_promo, 0, ',', '.') }}</td>
        </tr>
    </table>

    {{-- Riwayat Pembayaran --}}
    @if($invoice->pembayarans->isNotEmpty())
    <div style="margin-top: 15px;">
        <div style="font-weight: bold; margin-bottom: 4px; color: #166534; font-size: 11px;">Riwayat Pembayaran Diterima:</div>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <thead>
                <tr style="background-color: #f1f5f9;">
                    <th style="padding: 4px 6px; text-align: left; border: 1px solid #e2e8f0;">Tanggal Bayar</th>
                    <th style="padding: 4px 6px; text-align: left; border: 1px solid #e2e8f0;">Metode</th>
                    <th style="padding: 4px 6px; text-align: left; border: 1px solid #e2e8f0;">No. Referensi</th>
                    <th style="padding: 4px 6px; text-align: right; border: 1px solid #e2e8f0;">Jumlah</th>
                </tr>
            </thead>
            <tbody>
                @foreach($invoice->pembayarans as $bayar)
                <tr>
                    <td style="padding: 4px 6px; border: 1px solid #e2e8f0;">{{ $bayar->dibayar_pada->format('d/m/Y H:i') }}</td>
                    <td style="padding: 4px 6px; border: 1px solid #e2e8f0;">{{ $bayar->metode->label() }}</td>
                    <td style="padding: 4px 6px; border: 1px solid #e2e8f0;">{{ $bayar->referensi_transaksi ?: '-' }}</td>
                    <td style="padding: 4px 6px; border: 1px solid #e2e8f0; text-align: right;">Rp {{ number_format((float) $bayar->jumlah_dibayar, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Rekening & Penandatangan Grid --}}
    <table class="info-grid">
        <tr>
            <td style="width: 60%; padding-right: 15px;">
                @if($company->nomor_rekening)
                <div class="payment-box">
                    <div style="font-weight: bold; margin-bottom: 2px;">Instruksi Pembayaran Transfer Bank:</div>
                    Bank: <strong>{{ $company->nama_bank }}</strong><br>
                    No. Rekening: <strong style="font-size: 12px; color: #1e3a8a;">{{ $company->nomor_rekening }}</strong><br>
                    Atas Nama: <strong>{{ $company->atas_nama }}</strong>
                </div>
                @endif

                @if($company->catatan_invoice)
                <div style="margin-top: 8px; font-size: 10.5px; color: #475569;">
                    <strong>Catatan:</strong> {{ $company->catatan_invoice }}
                </div>
                @endif

                @if($company->syarat_ketentuan)
                <div style="margin-top: 4px; font-size: 10px; color: #64748b;">
                    <strong>Syarat & Ketentuan:</strong> {{ $company->syarat_ketentuan }}
                </div>
                @endif
            </td>
            <td style="width: 40%; text-align: center;">
                <div class="signature-box">
                    {{ $company->kota ?? 'Jakarta' }}, {{ $invoice->tanggal_terbit->format('d F Y') }}<br>
                    <strong>{{ $company->nama_perusahaan }}</strong>
                    <div class="signature-space"></div>
                    <strong style="text-decoration: underline;">{{ $company->nama_penandatangan ?? 'Finance & Billing Dept.' }}</strong><br>
                    <span>{{ $company->jabatan_penandatangan ?? 'Authorized Signature' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        Harap simpan invoice ini sebagai bukti tagihan resmi {{ $company->nama_brand }}. Dokumen ini dicetak otomatis oleh sistem komputer.
    </div>
</body>
</html>
