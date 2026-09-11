Sprint B3 (Kondisional) — Cek Kelayakan Batch Query Multi-Layanan
Konteks
Sprint B3 (batch query untuk pelanggan dengan beberapa layanan di router yang sama) hanya layak dikerjakan kalau kasus ini cukup sering terjadi di data produksi. Sebelum menulis kode apapun, jalankan pengecekan data berikut.
Langkah pengecekan (jalankan via Tinker atau query langsung, TANPA mengubah kode apapun)
PHP// Berapa banyak kombinasi pelanggan+router yang punya lebih dari 1 layanan aktif?
$jumlahKasus = LayananPelanggan::query()
    ->whereNull('deleted_at')
    ->select('pelanggan_id', 'router_id')
    ->groupBy('pelanggan_id', 'router_id')
    ->havingRaw('COUNT(*) > 1')
    ->get()
    ->count();

// Berapa total layanan aktif secara keseluruhan, untuk konteks proporsi?
$totalLayananAktif = LayananPelanggan::query()->whereNull('deleted_at')->count();

echo "Kombinasi pelanggan+router dengan >1 layanan: {$jumlahKasus}\n";
echo "Total layanan aktif: {$totalLayananAktif}\n";
echo "Proporsi: " . round(($jumlahKasus / max($totalLayananAktif, 1)) * 100, 2) . "%\n";




Kriteria keputusan

Proporsi < 5% dari total layanan, atau jumlah kasus absolut kecil (< 50) → skip Sprint B3 sepenuhnya. Manfaatnya tidak sepadan dengan effort implementasi + testing tambahan. Cukup dokumentasikan keputusan ini (dan angka yang ditemukan) di catatan tim, tidak perlu ticket baru.

Proporsi cukup signifikan (> 10-15%, atau ada beberapa pelanggan besar dengan banyak layanan di router yang sama) → lanjutkan Sprint B3, minta detail implementasi disusun terpisah (tidak dicakup di file ini, karena keputusannya bergantung angka aktual).
Yang harus dilaporkan setelah pengecekan
Angka $jumlahKasus, $totalLayananAktif, dan proporsinya.
Rekomendasi: lanjut ke implementasi Sprint B3 atau skip.
Kalau skip, tidak perlu langkah lanjutan — cukup catat di ringkasan project bahwa ini sudah dievaluasi dan hasilnya tidak signifikan (supaya tidak ditanyakan ulang di kemudian hari).
Kalau lanjut, minta disusun ulang detail Sprint B3 sebagai file terpisah (sprint-b3-implementasi-batch-query.md) dengan pola yang sama seperti sprint lain — jangan lompat langsung ke implementasi tanpa breakdown yang jelas.



