# ADR 0043: Pemisahan Menu Pengaturan Personal, Payment Gateway, dan WhatsApp Template dari Grup Administrasi

## Konteks
Sebelumnya `config/menu.php` menaruh "Pengaturan Gateway" di grup generik "Administrasi" (numpang permission `peran.lihat` alih-alih permission dedicated), dan nav lokal halaman settings (`x-settings.layout`) mencampur item akun pribadi (Profile/Security/Appearance) dengan item admin perusahaan (Perusahaan, Payment Gateway, WhatsApp Gateway) dalam satu list rata tanpa pemisahan. Ditemukan pula label "WhatsApp Gateway" pada `settings.whatsapp` menyesatkan — halaman itu sebenarnya CRUD Template Pesan WhatsApp (`WaTemplate`), bukan konfigurasi koneksi gateway; koneksi gateway sesungguhnya sudah ada terpisah di menu SysBlast > Koneksi API (`App\Models\Sysblas`).

## Keputusan yang Diambil

1. **Payment Gateway jadi item standalone di level atas sidebar** (bukan grup collapsible, karena cuma 1 item; bukan lagi anak grup Administrasi), dengan permission dedicated baru `payment_gateway.lihat/.buat/.ubah/.hapus` menggantikan `peran.lihat` yang dipinjam sebelumnya. Permission ini **sengaja tidak** diberikan ke role `admin` (tetap eksklusif `super_admin`), konsisten dengan postur akses hari ini terhadap kredensial pembayaran yang sensitif — bukan pengecualian baru.

2. **`settings.whatsapp` dipindah ke grup SysBlast** dan direlabel dari "WhatsApp Gateway" menjadi **"Template Pesan"**, dengan permission diubah dari `peran.lihat` ke `wa_gateway.lihat` (menyamai permission item SysBlast lainnya). Widget "Status Perangkat" dan "Uji Coba Kirim Pesan" di halaman itu dihapus karena duplikat fungsi dengan Koneksi API yang sudah lebih lengkap (QR pairing, start/stop/restart session, ping) — halaman ini sekarang murni CRUD template, lepas dari wrapper `<x-settings.layout>` karena bukan lagi bagian keluarga "Settings" personal.

3. **Nav lokal settings dipecah jadi 2 grup berlabel** (`flux:navlist.group`): "Akun Saya" (Profile/Security/Appearance — tetap ada di sini meski sudah bisa diakses lewat dropdown avatar, untuk kemudahan pindah antar tab tanpa mundur ke dropdown) dan "Pengaturan Perusahaan" (Perusahaan, Payment Gateway).

## Konsekuensi
- Menambah pengaturan admin baru ke depan punya preseden jelas: personal (dropdown avatar + nav lokal grup "Akun Saya") vs perusahaan/global (grup terkait di sidebar utama + grup "Pengaturan Perusahaan" bila relevan), bukan tumpuk semua di "Administrasi".
- Siapa pun yang menyentuh `settings.whatsapp` ke depan perlu tahu halaman ini murni template pesan, bukan koneksi — cek `App\Models\Sysblas`/menu Koneksi API untuk itu.
