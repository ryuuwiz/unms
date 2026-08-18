# Dashboard
* Total Pelanggan (Aktif dan Tidak Aktif)
* Pendapatan hari ini dan bulan ini
* Ringkasan Tagihan Periode Bulan ini
* Tren pendapatan harian dan transaksi harian
* Transaksi terbaru
* Pelanggan Expired
---
# MAPS
## Fitur Data Maps
Pantau titik pelanggan, layanan, perumahan, dan ODP yang sudah memiliki koordinat.
* Data titik Pelanggan
* Data titik layanan
* Data perumahan dengan maps
* Data ODP dengan maps
* Maps dan Filter maps
## Estimasi Kabel
Hitung jarak titik survey ke ODP terdekat secara terpisah dari Maps Lokasi.
* Peta Survey
* Parameter Hitung
	* Latitude
	* Longitude
	* Filter nama ODP/PON/keterangan
	* Jumlah ODP, Faktor, Reserve
	* Cari ODP Terdekat
	* Gunakan Lokasi saya
	* Hasil
---
# Alamat
## Kota
Pengelolaan data kota untuk pelanggan UNMS.
* ID Kota
* Nama Kota
* Keterangan
## Kecamatan
Pengelolaan data kecamatan beserta kota terkait.
* ID Kecamatan
* Nama Kecamatan
* Nama Kota
* Keterangan
## Kelurahan
Pengelolaan data kelurahan, kecamatan, dan kota.
* ID Kelurahan
* Kelurahan
* Kecamatan
* Kota
* Keterangan
## Perumahan/Cluster
Pengelolaan data perumahan / cluster beserta relasi kelurahan & kecamatan.
* ID Perumahan
* Perumahan/Cluster
* Kelurahan
* Kecamatan
* Nama Kota
* Keterangan
* Singkatan
---
# Kontak Member
Kelola data pelanggan beserta alamat dan titik lokasi pemasangan.
## Form Tambah Pelanggan
* Tipe Pelanggan (Home, Business)
* NIK (16 Digit)
* Email
* Nama Lengkap (Nama Depan, Nama Belakang)
* Nomor HP
* Telepon Rumah
* Perumahan/Cluster
* RW
* RT
* NO.
* Kode Pos
* Alamat Lengkap
* Latitude
* Longitude
## Kelola Kontak
Daftar seluruh pelanggan internet beserta status pemasangan & aktivasi.
* No. Reg
* Nama Pelanggan
* No. HP
* Email
* Dibuat (Timestamp)
* Status
### Detail Pelanggan
* Data Pelanggan
	* Nomor Reg
	* Nomor HP
	* Email
	* Tlp Rumah
	* NIK
	* Tipe Pelanggan
	* Nama Pelanggan
	* Kode Pembayaran
* Alamat & Lokasi Pelanggan
	* Alamat Lengkap
	* Alamat Terstruktur
	* Latitude
	* Longitude
	Peta hanya untuk tampilan lokasi pelanggan. Perubahan titik dilakukan dari menu edit pelanggan.
* Akun Portal Pelanggan
	Pelanggan memiliki akun user
		* username (email)
		* password (default: 12345678)
* Data KTP
	* Gambar KTP
* Layanan / Pemasangan
	* Label Layanan
	* PPP Username
	* Paket / Router
	* Status
* Tagihan Aktif (Belum Lunas / Pending)
	* No. Invoice
	* Layanan
	* Detail
	* Tanggal
	* Metode
	* Teller/Ref
	* Jumlah
	* Promo
	* Status
	* Proses
* Riwayat Pembayaran Lunas
	* No. Invoice
	* Layanan
	* Detail
	* Tanggal
	* Metode
	* Teller/Ref
	* Jumlah (Rp.)
	* Promo
	* Status (Contoh: Lunas)
* Riwayat Invoice Dihapus
	* No. Invoice
	* Layanan
	* Detail 
	* Tanggal
	* Jumlah
	* Dihapus Oleh
	* Keterangan
---
# Ticket
Manajemen ticket pemasangan, gangguan, pencabutan, pindah alamat, dan lainnya.
* ID
* Waktu
* Pelanggan (Nama dan No. Reg)
* Layanan (PPP Username, Jenis (Contoh: PPPOE, IP Static))
* Jenis (Pemasangan, Pencabutan, Gangguan)
### Detail Ticket
* Ringkasan Ticket
	* Jenis
	* Prioritas
	* Divisi (Contoh: Admin, Customer Service, Sales)
	* Dibuat (Timestamp)
	* Jadwal (Timestamp)
	* PIC
	* Infra
	* Layanan (PPP Username, Site ID, Jenis (Contoh: PPPOE, IP Static))
* Deskripsi Tiket
* Histori Proses Tiket
* Informasi Pelanggan
* Informasi Layanan
* Sales yang menangani
* Alamat Layanan
---
# Administrasi
## Laporan Harian
* Tanggal
* Ringkasan Pendapatan Harian
	* Total Pendapatan untuk tanggal (Date now)
	* Total transaksi hari ini (N transaksi)
	* Waktu generate laporan (time)
* Semua Transaksi pada Tanggal (Date Now)
	* Invoice
	* Layanan / Site ID
	* No. Registrasi
	* Nama Lengkap
	* Nama Paket
	* Jumlah
	* Tanggal Bayar
	* Tanggal Expired
	* Metode Pembayaran (Contoh: IPaymu) → Payment Gateway
	* Sales / Teller (Contoh: "va - bca", "qris - linkaja") → Payment Gateway
## Laporan Periode
* Tanggal Mulai
* Tanggal Selesai
* Tipe Transaksi (PPPOE, IP Static)
* Ringkasan Pendapatan
### Semua Transaksi pada Tanggal
* Invoice
* Layanan / Site ID
* No. Reg
* Jenis (PPPOE, IP Static)
* Nama Paket (Contoh: "25/MIIX/BF/FTTH_[[LITE]][[30Mbps]]"
* Deskripsi Paket
* Harga
* Tanggal Registrasi
* Tanggal Expired
* Metode (Contoh: admin, ipaymu)
* Routers
## Laporan Keuangan Bulanan
* Total Transaksi
* Total Amount
* Promo & Resiko
* Skor Kesehatan Keuangan
	* Keunggulan
		```
		Konsentrasi pelanggan aman (Top10 share 5.21%).
		Tidak ada transaksi terhapus pada periode ini.
		Pola bayar dominan: lebih awal (sebelum jatuh tempo) (Before/On/After/Unknown: 725/185/179/0).
		Rata-rata jarak bayar ke jatuh tempo: 4.54 hari (positif = lebih awal, negatif = telat).
		```* Risiko
		```
		Collection rate rendah (71.16%) → unpaid amount Rp 90.580.000.
		Banyak invoice unpaid pada kelompok 'belum memilih/metode tidak tercatat' (rate 100%, trx 432). Ini indikasi alur bayar & komunikasi pembayaran perlu diperkuat.
		Ketergantungan tinggi pada router 'R_ARSYILA_PSN_PUSAT' (share 77.53% dari total).
		```* Ringkasan Billing
	* Service New
	* Renewals
	* Expiring
	* Status Layanan
* Metode Pembayaran
	* Method (Contoh: ipaymu, admin)
	* Jumlah
* Tipe Pelayanan
	* Tipe (PPPOE, IP Static)
	* Jumlah
* Router
	* Router
	* Jumlah
* Grafik Metode Pembayaran (Ipaymu, Admin, etc.)
* Grafik Tipe (PPPOE, IP Static)
* Grafik Router (Top 10)
* Trend Amount (Harian)
* Paid vs Unpaid Amount
* Top Customers (by amount)
	* Customer key (no_reg/username)
	* trx
	* amount
* Hasil Analisis & Rekomendasi
* Kesimpulan
* Analisis Timing Pembayaran
## Laporan Ticket Bulanan
## Transaksi Payment Gateway
* Kode Transaksi
* Gateway
* TRX ID
* Pay channel
* Date update
* Total Bayar + Admin
* Status
### Detail Pembayaran Payment Gateway
* Detail Pelanggan & Invoice
	* Data Pelanggan
		* Nomor Reg
		* Nama
		* E-Mail
		* Nomor HP
	* Invoice yang dibayar pada transaksi ini
		* Nomor Invoice
		* Status
		* Nominal
		* Update terakhir
		* Metode Pembayaran (contoh: "ipaymu", "xendit")
		* Sales (contoh: "va - bca")
	* Total nominal invoice
	* Total tagihan payment gateway  dan fee
	* Kode INV
* Informasi Payment Gateway
	* Session ID
	* Transaction ID
	* Reference ID
	* Expired
	* Status
	* Via (Contoh: "VA")
	* Channel (Contoh: "BCA")
	* Payment Number
	* Total
	* Fee
	* Catatan
* Log Webhook Payment Gateway
	* Tanggal
	* Invoice
	* Status
	* Response (contoh: "trx_id=xxxxxxx"
## Manajemen Promo Billing
Atur promo bonus bulan & diskon pembayaran invoice.
* Kode Promo
	Kode ini akan dimasukkan saat pembayaran invoice, contoh: `HEMAT10`, `BAYAR12GRATIS3`.
* Nama / Judul
	Contoh: `Bayar 12 Bulan Gratis 3 Bulan` atau `Diskon 10% Invoice`.
* Jenis Promo
	* **Bonus Durasi:** cocok untuk promo seperti "Bayar 12 bulan bonus 3 bulan".
	* **Diskon:** cocok untuk promo seperti "Diskon 10% saat bayar invoice".
* URL gambar Promo
	Masukkan URL penuh gambar (bisa dari CDN / storage kamu).
* Pratinjau gambar promo
	Masukkan URL gambar, lalu pratinjau akan muncul di sini.
* Deskripsi Promo
* Aturan bonus/diskon
* Bayar (bulan)
	Untuk promo durasi: "bayar N bulan". Untuk diskon: boleh dikosongkan jika tidak terkait bulan.
* Bonus Bulan
	**Hanya dipakai bila jenis = Bonus Durasi.** Contoh: bayar 12 dapat gratis 3 → isikan `3`.
* Diskon
	* Jika **Persentase**: isi misalnya `10` untuk 10%.
	* Jika **Nominal**: isi misalnya `50000` untuk Rp 50.000.
* Minimal Nominal Invoice (Rp) (opsional)
	Digunakan terutama untuk promo diskon. Contoh: `200000` artinya invoice minimal Rp 200.000 agar promo bisa dipakai.
* Kuota Pemakaian
	* Kuota Global
		Kosongkan untuk tanpa batas kuota global.
	* Kuota per Pelanggan
		Berapa kali 1 pelanggan boleh pakai promo ini. Kosongkan untuk tanpa batas.
	* Informasi
		Terpakai (global): **0** kali
* Periode Berlaku
	Kosongkan jika promo selalu bisa digunakan (dibatasi hanya oleh kuota & status aktif).
	* Berlaku dari (dd/mm/yyyy)
	* Sampai dengan (dd/mm/yyyy)
* Checkbox: 
	* Promo aktif dan bisa digunakan saat pembayaran
	* Tampilkan promo ini ke customer (portal/client area)
## SysBlast - Koneksi API
Kelola koneksi GOWA / WhatsApp / gateway lain untuk blast pesan.
* API
* Nomor/Label
* URL API
* Default 
* Status
* Limit (MSG/Menit)
* Keterangan
### Antrian Blast SysBlast
Monitoring antrian pesan WhatsApp/SMS yang dikirim lewat SysBlast.
* Waktu Request
* Nomor
* Pesan
* Status (Terkirim, Gagal)
* Jenis (Contoh: "Chat")
* Broadcast (Contoh: "Client API")
* API
* Jadwal
---
# Layanan & Network
## Billing
* Username (PPP username)
* Site ID
* Infra
* Nama Paket
* Router
* Tanggal Awal Regis
* Expired + Status (Datetime + (Aktif, Proses, Suspend))
Detail Billing → Detail Pelanggan
## Layanan
### Manajemen Paket
Kelola paket layanan PPPoE untuk pelanggan.
* Nama Paket
	Hanya boleh: huruf, angka, underscore (_), tanda [[ ]], slash (/), dan minus (-).
* Nama Bandwith
* Harga
	Masukkan angka saja. Minimal Rp 10.000 dan maksimal Rp 50.000.000.
* Masa Aktif (Hari, Bulan)
	Contoh: 30 Hari, 1 Bulan, 12 Bulan, dll.
* Keterangan
	Opsional. Bisa diisi detail FUP, SLA, batas penggunaan, catatan internal, dll.
### Daftar Bandwith
Daftar profil bandwidth untuk paket Hotspot / PPPoE.
* Nama bandwith
	Nama profil, 4–30 karakter. Contoh: `10M/2M-HOME`.
* Max Limit (TX/RX)
	Batas maksimal kecepatan rata-rata (rate-limit).
	* TX (Mbps)
	* RX (Mbps)
* Burst Rate (TX / RX)
	Kecepatan burst maksimal saat kondisi naik.
	* TX (Mbps)
	* RX (Mbps)
* Burst Threshold (TX / RX)
	Ambang batas rata-rata sebelum burst dihentikan.
	* TX (Mbps)
	* RX (Mbps)
* Burst Time (TX / RX)
	Lama waktu burst aktif dalam detik (hanya angka).
	* TX (Mbps)
	* RX (Mbps)
* Limit Rate (TX / RX)
	Nilai maksimal absolut (mirip max-limit tambahan).
	* TX (Mbps)
	* RX (Mbps)
* Priority (1-8, 1 Highest → 8 Lowest)
	Prioritas queue (1 tertinggi, 8 terendah).
## Network
### Routers
Manajemen perangkat router / Mikrotik yang terhubung ke sistem billing. Pastikan IP, username, dan password sesuai dengan konfigurasi router (misalnya Mikrotik). 
* Nama Router
	Hanya huruf besar (A–Z), angka (0–9), dan underscore (`_`).
* IP Address
	Bisa IPv4 atau IPv6, harus dapat diakses dari server billing.
* Username
	User yang memiliki akses cukup untuk pengelolaan profil PPP, queue, dll.
* Password Router
	Gunakan password khusus untuk sistem bila memungkinkan.
* Deskripsi
	Lokasi, Fungsi, catatan lain (contoh: Core router kantor pusat, link ke POP A/B, etc.)
### IP Pool
Manajemen IP Pool & Simple Queue untuk layanan PPPoE.
Masukkan IP Network dan CIDR yang valid, lalu klik "Gunakan range saran" untuk mengisi otomatis.
```
Catatan:
Gunakan IP Calculator untuk verifikasi network dan range IP.
Netmask diisi dengan format CIDR (misal 24 untuk 255.255.255.0).
Range IP berisi alamat yang akan dialokasikan untuk client PPPoE. Bila dikosongkan, sistem hanya menyimpan IP Network & Queue.
Sistem akan otomatis menambahkan Simple Queue dengan IP Network dan batasan bandwidth yang kamu input.
```
* Nama Pool
	Hanya huruf besar, angka, dan underscore. Contoh: `PPP_POOL_RUMAH`.
* IP Network
	* IP (Contoh: 192.168.088.000)
	* CIDR (Contoh: 24) 
		Gunakan CIDR (`/24`, `/23`, dst) sesuai desain jaringan.
* Rentang IP
	Rentang IP yang akan dipakai untuk client PPPoE. Opsional, tapi bila diisi harus format `IP1-IP2`.
	Contoh: 192.168.88.2-192.168.88.254 (Button Gunakan range saran)
* Queue Limit
	Satuan dalam Mbps, nanti otomatis dikonversi menjadi bit/s di Mikrotik.
		* TX
		* RX
* Priority Queue
	Nilai antara 1 (tertinggi) sampai 8 (terendah). Default: 8/8.
		* Pry TX
		* Pry RX
* Routers
	IP Pool dan Queue akan dibuat di router ini.

