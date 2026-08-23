# ADR 0016: Konversi Bandwidth Biner (Mbps ke bps) dan Format Rate-Limit RouterOS

## Konteks
Sistem UNMS sebelumnya menggunakan format string dengan suffix unit `M` (misal `"20M/20M"`) saat mem-provisi PPP Profile ke RouterOS MikroTik. Di RouterOS, notasi `M` dibaca sebagai desimal base-10 ($1\text{M} = 1.000.000\text{ bps}$).

Namun, pada implementasi jaringan ISP riil, enkapsulasi paket data (PPPoE header 8 byte, IP header 20 byte, TCP header 20-32 byte, Ethernet frame 14-18 byte) menimbulkan *encapsulation overhead* sekitar 3% sampai 5%. Akibatnya, pelanggan yang berlangganan paket 20 Mbps hanya akan mendapatkan hasil pengujian kecepatan (*Speedtest*) sekitar **18.8 Mbps – 19.2 Mbps** jika router di-limit pas $20.000.000\text{ bps}$. Hal ini kerap memicu komplain pelanggan bahwa kecepatan tidak mencapai batas paket yang dibeli.

Untuk memberikan alokasi throughput riil yang menutup overhead dan memuaskan pelanggan, ISP menerapkan standar perhitungan biner ($1\text{ Mbps} = 1024 \times 1024 = 1.048.576\text{ bps}$). Contohnya, paket 20 Mbps diatur di RouterOS menjadi $20 \times 1.048.576 = \mathbf{20.971.520\text{ bps}}$.

## Keputusan yang Diambil

1. **Penyimpanan Database & Antarmuka Staf Tetap Mbps Murni**:
   - Form input pada UI Livewire dan seluruh kolom kecepatan pada tabel `profil_bandwidth` (`max_limit_tx`, `max_limit_rx`, `burst_rate_tx`, `burst_rate_rx`, `burst_threshold_tx`, `burst_threshold_rx`, `limit_rate_tx`, `limit_rate_rx`) tetap menggunakan integer **Mbps** bulat (contoh: `20`).
   - Staf NOC tidak perlu menghitung angka jutaan bps secara manual saat membuat profil bandwidth.

2. **Formula Pengali Biner ($1\text{ Mbps} = 1.048.576\text{ bps}$)**:
   - Sistem menggunakan faktor pengali $1024 \times 1024 = 1.048.576$ untuk mengonversi nilai Mbps menjadi bits per second (bps).

3. **Format Payload String Rate-Limit MikroTik RouterOS**:
   - Sinkronisasi PPP Profile dan Queue ke RouterOS mengirimkan format string numerik bps murni (tanpa suffix `M`):
     - **Non-Burst**: `"{tx_bps}/{rx_bps}"` (contoh untuk 10M TX / 20M RX: `"10485760/20971520"`).
     - **Burst**: `"{max_tx_bps}/{max_rx_bps} {burst_tx_bps}/{burst_rx_bps} {threshold_tx_bps}/{threshold_rx_bps} {burst_time_tx}/{burst_time_rx} {priority} {limit_tx_bps}/{limit_rx_bps}"`.
     - Parameter durasi waktu burst (`burst_time` dalam detik) dan `priority` (1–8) tetap berupa angka integer standar tanpa pengali bps.

4. **Helper Terpusat `BandwidthConverter`**:
   - Dibuat kelas helper `App\Support\BandwidthConverter` (atau method pada model/support) untuk menangani konversi dua arah secara terpusat:
     - `mbpsToBps(int $mbps): int`
     - `bpsToMbps(int|float $bps): float`
     - `formatHumanReadable(int|float $bps): string` (berguna untuk monitoring traffic / status antrian RouterOS).

## Konsekuensi
- Hasil Speedtest pelanggan mencapai target paket riil (~20.0 - 20.3 Mbps pada paket 20 Mbps) karena overhead TCP/IP & PPPoE tertutup oleh buffer alokasi biner.
- Komplain pelanggan terkait kecepatan tidak tembus limit dapat ditekan secara signifikan.
- Konfigurasi PPP profile dan Simple Queue di RouterOS terstandarisasi dalam satuan numerik bps murni yang presisi.
- Model dan test suite UNMS diperbarui untuk memverifikasi payload string numerik bps ke RouterOS.
