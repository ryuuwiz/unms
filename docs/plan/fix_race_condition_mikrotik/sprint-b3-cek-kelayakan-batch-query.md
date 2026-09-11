# Sprint B3 (Kondisional) — Cek Kelayakan Batch Query Multi-Layanan

## Konteks

Sprint B3 (batch query untuk pelanggan dengan beberapa layanan di router yang sama) hanya layak dikerjakan kalau kasus ini cukup sering terjadi di data produksi. Sebelum menulis kode apapun, jalankan pengecekan data berikut.

Latar belakang teknis: saat ini `HandleLayananStatusChangedListener` men-dispatch job Mikrotik (`EnablePppoeAccountJob`, `DisablePppoeAccountJob`, `ProvisionPppoeAccountJob`) satu per satu per `LayananPelanggan`, dan `uniqueId()` job-job tersebut di-key per `router_id:layanan_id` (bukan per router). Kalau satu pelanggan punya beberapa layanan aktif di router yang sama dan semuanya berubah status bersamaan (mis. lewat `CheckLayananIsolirCommand`), ini menghasilkan job terpisah yang masing-masing membuka koneksi Mikrotik sendiri ke router yang sama — inilah biaya nyata yang coba diukur proporsinya di sini. Sprint B2 (cache `getPppStatus`) sudah menge-cache jalur baca status, tapi jalur tulis (enable/disable/provision) ini belum tersentuh mitigasi apapun.

## Langkah pengecekan (jalankan via Tinker atau query langsung, TANPA mengubah kode apapun)

```php
use App\Models\LayananPelanggan;
use App\Enums\StatusLayanan; // sesuaikan namespace enum bila berbeda

// Berapa banyak kombinasi pelanggan+router yang punya lebih dari 1 layanan?
// (semua layanan non-deleted, untuk gambaran umum)
$jumlahKasus = LayananPelanggan::query()
    ->whereNull('deleted_at')
    ->select('pelanggan_id', 'router_id')
    ->groupBy('pelanggan_id', 'router_id')
    ->havingRaw('COUNT(*) > 1')
    ->get()
    ->count();

// Versi yang lebih representatif terhadap pemicu nyata (isolir/aktivasi massal
// hanya menyentuh layanan berstatus Aktif) — pakai ini sebagai angka utama.
$jumlahKasusAktif = LayananPelanggan::query()
    ->whereNull('deleted_at')
    ->where('status', StatusLayanan::Aktif)
    ->select('pelanggan_id', 'router_id')
    ->groupBy('pelanggan_id', 'router_id')
    ->havingRaw('COUNT(*) > 1')
    ->get()
    ->count();

// Konsentrasi terburuk: kombinasi pelanggan+router dengan JUMLAH layanan aktif
// TERBANYAK. Proporsi keseluruhan bisa kecil, tapi satu pelanggan korporat/reseller
// dengan puluhan layanan di satu router bisa memicu lonjakan koneksi/job ke router
// itu saat isolir massal — sinyal ini tidak tertangkap oleh proporsi agregat.
$konsentrasiTerburuk = LayananPelanggan::query()
    ->whereNull('deleted_at')
    ->where('status', StatusLayanan::Aktif)
    ->select('pelanggan_id', 'router_id')
    ->selectRaw('COUNT(*) as jumlah_layanan')
    ->groupBy('pelanggan_id', 'router_id')
    ->havingRaw('COUNT(*) > 1')
    ->orderByDesc('jumlah_layanan')
    ->limit(10)
    ->get();

// Berapa total layanan aktif secara keseluruhan, untuk konteks proporsi?
$totalLayananAktif = LayananPelanggan::query()
    ->whereNull('deleted_at')
    ->where('status', StatusLayanan::Aktif)
    ->count();

echo "Kombinasi pelanggan+router dengan >1 layanan (semua status): {$jumlahKasus}\n";
echo "Kombinasi pelanggan+router dengan >1 layanan AKTIF: {$jumlahKasusAktif}\n";
echo "Total layanan aktif: {$totalLayananAktif}\n";
echo "Proporsi (berbasis layanan aktif): " . round(($jumlahKasusAktif / max($totalLayananAktif, 1)) * 100, 2) . "%\n";
echo "Top 10 konsentrasi terburuk (pelanggan_id, router_id, jumlah_layanan):\n";
foreach ($konsentrasiTerburuk as $row) {
    echo "  pelanggan={$row->pelanggan_id} router={$row->router_id} jumlah={$row->jumlah_layanan}\n";
}
```

Catatan: query `groupBy('pelanggan_id', 'router_id')` di atas tidak didukung index composite (hanya ada index single-column dari foreign key) — untuk pengecekan sekali jalan ini bukan masalah, tapi jangan dijadwalkan rutin tanpa menambah index kalau nanti dipakai berulang.

## Kriteria keputusan

- Proporsi (berbasis layanan aktif) **< 5%** dari total layanan aktif, **dan** jumlah kasus absolut kecil (< 50), **dan** konsentrasi terburuk tidak menunjukkan pelanggan besar dengan puluhan layanan di satu router → **skip Sprint B3 sepenuhnya**. Manfaatnya tidak sepadan dengan effort implementasi + testing tambahan.
- Proporsi cukup signifikan (**> 10-15%**), **atau** hasil "konsentrasi terburuk" menunjukkan satu/lebih pelanggan dengan banyak layanan (mis. ≥ 5-10) di router yang sama → **lanjutkan Sprint B3**, minta detail implementasi disusun terpisah (tidak dicakup di file ini, karena keputusannya bergantung angka aktual).

## Yang harus dilaporkan setelah pengecekan

- Angka `$jumlahKasus`, `$jumlahKasusAktif`, `$totalLayananAktif`, proporsinya, dan daftar top 10 konsentrasi terburuk.
- Rekomendasi: lanjut ke implementasi Sprint B3 atau skip.
- **Kalau skip**: catat keputusan ini beserta angka yang ditemukan lewat `record-rule` (Boost MCP) dengan glob yang mencakup `app/Listeners/HandleLayananStatusChangedListener.php` dan/atau `app/Services/Mikrotik/**`, supaya agent berikutnya tidak menanyakan ulang. Tidak perlu ticket baru.
- **Kalau lanjut**: minta disusun ulang detail Sprint B3 sebagai file terpisah (`sprint-b3-implementasi-batch-query.md`) dengan pola yang sama seperti sprint lain — jangan lompat langsung ke implementasi tanpa breakdown yang jelas.
