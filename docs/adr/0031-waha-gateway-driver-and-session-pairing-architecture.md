# ADR 0031: Arsitektur Driver Gateway WAHA, Live Session Pairing, dan Webhook Event Ingestion

## Konteks
Sistem komunikasi pelanggan dan blast tagihan otomatis di GOBILLING sebelumnya bergantung pada provider WABLAS eksternal. Untuk meningkatkan reliabilitas, mengeliminasi biaya pihak ketiga, dan memungkinkan kontrol penuh atas sesi WhatsApp internal, infrastruktur WhatsApp dimigrasikan ke **WAHA (WhatsApp HTTP API v2026.8.1)**.

Tantangan utama yang dihadapi:
1. **Multi-Provider Extensibility**: Database `sysblas` mendukung multi-gateway (`waha`, `wablas`, `gowa`, `sms`). Kode tidak boleh terikat secara kaku (*hardcoded*) pada satu provider tanpa kontrak abstraksi.
2. **Session Pairing Lifecycle**: Staf operasional membutuhkan kemudahan pairing WhatsApp Web (scan QR Code, start, stop, restart, logout) langsung dari portal GOBILLING tanpa perlu membuka antarmuka Swagger/server backend WAHA.
3. **Webhook DLR & Incoming Message Ingestion**: WAHA mengirimkan notifikasi berbasis event (`message.ack`, `message`, `session.status`) yang membutuhkan parsing terstruktur untuk memperbarui status antrean (`AntrianWaBlast`), mencatat pesan masuk, dan memberikan balasan otomatis (*smart self-service bot*).
4. **Multi-Session Scalability**: Kebutuhan memisahkan nomor WhatsApp pengiriman tagihan (billing blast) dari nomor layanan pengaduan / tiket kendala pelanggan pada satu server WAHA yang sama.

## Keputusan yang Diambil

1. **Driver & Contract Abstraction Pattern**:
   - Dibuat interface kontrak `WhatsappGatewayDriverInterface` yang mendefinisikan metode standar: `sendMessage`, `pingConnection`, `getDeviceInfo`, `getQrCode`, `startSession`, `stopSession`, `restartSession`, `logoutSession`, dan `checkNumberStatus`.
   - `WahaDriver` mengimplementasikan seluruh endpoint native WAHA (`/api/sendText`, `/api/sessions/{session}`, `/api/{session}/auth/qr`, `/api/contacts/check-exists`).
   - `WablasDriver` mempertahankan kompatibilitas mundur untuk koneksi WABLAS/GOWA.
   - `WhatsappClient` bertindak sebagai client facade terpadu yang mendelegasikan pemanggilan ke driver yang sesuai berdasarkan kolom `provider` di model `Sysblas`.

2. **Dukungan Multi-Session WAHA di Database**:
   - Ditambahkan kolom `session_name` (`string`, default `'default'`) pada tabel `sysblas` melalui migrasi `2026_08_31_160000_add_session_name_to_sysblas_table`.
   - Memungkinkan pengelolaan beberapa sesi WhatsApp independen (misal: session `billing` dan session `support`) dalam satu database GOBILLING.

3. **Live QR Code Pairing Modal di Antarmuka Staf**:
   - Komponen Livewire `App\Livewire\Sysblas\Koneksi\Index` dilengkapi modal scan QR Code interaktif dengan auto-polling (`wire:poll.3s`).
   - Modal menampilkan QR Code image secara langsung dan mendeteksi transisi status session dari `SCAN_QR_CODE` ke `WORKING` secara real-time.
   - Tersedia tombol kontrol sesi: `Start`, `Stop`, `Restart`, dan `Logout`.

4. **Event-Driven Webhook Processing**:
   - Endpoint `POST /api/webhook/whatsapp` (dan alias `/api/webhook/wablas`) memproses payload event WAHA:
     - `message.ack`: Memetakan status ACK (1=sent, 2=delivered, 3=read, error) untuk mengupdate status record `AntrianWaBlast` secara akurat.
     - `session.status`: Mengupdate status `is_aktif` pada koneksi `Sysblas` saat sesi berstatus `WORKING` atau `STOPPED`.
     - `message`: Membaca pesan masuk pelanggan (mengabaikan `fromMe`), mencatat riwayat ke tiket aktif (jika ada), dan memberikan balasan cerdas berisi rincian tagihan belum lunas beserta direct Payment Gateway link (Xendit/Portal).

5. **Pengiriman Tagihan & Rate Limiting**:
   - Pengiriman tagihan otomatis dikelola melalui scheduled command `invoice:kirim-pengingat` dan queue worker `wa-blast` pada Horizon dengan proteksi rate limiter per gateway (`limit_per_menit`). Pesan tagihan memuat link pembayaran langsung dari payment gateway yang terkonfigurasi.

## Konsekuensi
- **Kemudahan Operasional**: Staf dapat menghubungkan nomor WhatsApp baru hanya dalam hitungan detik melalui antarmuka web GOBILLING.
- **Transparansi Pengiriman (DLR)**: Sistem memiliki visibilitas lengkap atas pesan yang berhasil diterima dan dibaca oleh pelanggan.
- **Layanan Mandiri 24/7**: Pelanggan dapat mengecek tagihan dan status tiket secara interaktif melalui WhatsApp tanpa membebani CS.
- **Arsitektur Modular**: Penambahan provider baru di masa depan cukup dengan mengimplementasikan `WhatsappGatewayDriverInterface`.
