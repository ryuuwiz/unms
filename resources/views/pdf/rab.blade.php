<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>RAB Kantor {{ $periode->translatedFormat('F Y') }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #333; font-size: 12px; margin: 0; padding: 15px 20px; }
        .header { border-bottom: 2px solid #2563eb; padding-bottom: 12px; margin-bottom: 15px; }
        .header table { width: 100%; border-collapse: collapse; }
        .company-logo { max-height: 50px; max-width: 180px; margin-bottom: 5px; }
        .company-name { font-size: 18px; font-weight: bold; color: #1e40af; }
        .company-details { font-size: 10.5px; color: #64748b; }
        .title { font-size: 20px; font-weight: bold; text-align: right; color: #1e3a8a; }
        .items { width: 100%; border-collapse: collapse; }
        .items th, .items td { border: 1px solid #e2e8f0; padding: 6px 8px; }
        .items th { background: #f1f5f9; text-align: left; }
        .right { text-align: right; }
        .total td { font-weight: bold; background: #f8fafc; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td style="width: 60%; vertical-align: top;">
                    @if ($perusahaan->logo_base64)
                        <div><img src="{{ $perusahaan->logo_base64 }}" class="company-logo" alt="{{ $perusahaan->nama_brand }}" /></div>
                    @endif
                    <div class="company-name">{{ $perusahaan->nama_perusahaan }}</div>
                    <div class="company-details">
                        @if ($perusahaan->alamat){{ $perusahaan->alamat }}@endif
                        @if ($perusahaan->kota), {{ $perusahaan->kota }}@endif
                    </div>
                </td>
                <td style="width: 40%; vertical-align: top;">
                    <div class="title">RAB KANTOR</div>
                    <div class="right">{{ $periode->translatedFormat('F Y') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>No</th>
                <th>Uraian</th>
                <th class="right">Qty</th>
                <th class="right">Harga</th>
                <th class="right">Jumlah</th>
                <th>Divisi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->uraian }}</td>
                    <td class="right">{{ number_format($item->qty, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($item->harga, 0, ',', '.') }}</td>
                    <td class="right">Rp {{ number_format($item->jumlah(), 0, ',', '.') }}</td>
                    <td>{{ $item->labelDivisi() }}</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align: center;">Belum ada item RAB.</td></tr>
            @endforelse
            <tr class="total">
                <td colspan="4">TOTAL</td>
                <td class="right">Rp {{ number_format($grandTotal, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>
</body>
</html>
