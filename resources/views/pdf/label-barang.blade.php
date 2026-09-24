<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Barang</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        td { width: 33.33%; height: 30mm; padding: 2mm; text-align: center; vertical-align: middle; border: 0.2mm dashed #bbb; }
        .kode { font-family: DejaVu Sans Mono, monospace; font-size: 9pt; font-weight: bold; margin-top: 1mm; }
        .nama { color: #444; font-size: 7pt; }
        img { max-width: 58mm; height: 12mm; }
    </style>
</head>
<body>
    <table>
        @foreach ($labels->chunk(3) as $baris)
            <tr>
                @foreach ($baris as $label)
                    <td>
                        <img src="data:image/png;base64,{{ $label['barcode'] }}" alt="{{ $label['kode'] }}">
                        <div class="kode">{{ $label['kode'] }}</div>
                        <div class="nama">{{ $label['nama'] }}</div>
                    </td>
                @endforeach
                @for ($i = $baris->count(); $i < 3; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
