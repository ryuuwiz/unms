<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Barang {{ $barang->kode_barang }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 24px;
            background: #f4f4f5;
        }
        .toolbar {
            max-width: 320px;
            margin: 0 auto 16px;
            text-align: right;
        }
        .toolbar button {
            padding: 8px 16px;
            border-radius: 8px;
            border: 1px solid #d4d4d8;
            background: #18181b;
            color: #fff;
            cursor: pointer;
            font-size: 14px;
        }
        .label {
            width: 320px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #d4d4d8;
            border-radius: 12px;
            padding: 16px;
            text-align: center;
        }
        .label .nama {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            word-wrap: break-word;
        }
        .label svg {
            width: 100%;
            height: auto;
        }
        .label .kode {
            font-family: monospace;
            font-size: 13px;
            font-weight: 700;
            margin-top: 4px;
            letter-spacing: 1px;
        }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .label { border: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Cetak Label</button>
    </div>

    <div class="label">
        <div class="nama">{{ $barang->nama_barang }}</div>
        {!! $barang->barcodeSvg() !!}
        <div class="kode">{{ $barang->kode_barang }}</div>
    </div>
</body>
</html>
