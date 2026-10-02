<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label Port</title>
    <style>
        @page { margin: 1.5mm 2mm; }
        body { font-family: DejaVu Sans, sans-serif; margin: 0; color: #000; }
        .label { page-break-after: always; }
        .label:last-child { page-break-after: auto; }
        .port { font-size: 10pt; font-weight: bold; line-height: 1.15; border-bottom: 0.3mm solid #000; padding-bottom: 0.6mm; margin-bottom: 0.8mm; }
        /* Nama ODP panjang memakai huruf lebih kecil agar label tetap muat satu halaman 50×30 mm. */
        .port.kecil { font-size: 7.5pt; }
        /* Nama maksimal 2 baris: dibatasi jumlah karakternya di bawah (dompdf memotong overflow tidak rapi). */
        .nama { font-size: 7.5pt; font-weight: bold; line-height: 1.2; }
        .meta { font-family: DejaVu Sans Mono, monospace; font-size: 7pt; line-height: 1.3; margin-top: 0.6mm; }
    </style>
</head>
<body>
    @foreach ($labels as $label)
        <div class="label">
            @php($teksPort = "{$label['odp']} · Port {$label['port']}")
            <div @class(['port', 'kecil' => mb_strlen($teksPort) > 20])>{{ $teksPort }}</div>
            <div class="nama">{{ \Illuminate\Support\Str::limit($label['nama'], 34) }}</div>
            <div class="meta">{{ $label['no_reg'] }}<br>{{ $label['site_id'] }}</div>
        </div>
    @endforeach
</body>
</html>
