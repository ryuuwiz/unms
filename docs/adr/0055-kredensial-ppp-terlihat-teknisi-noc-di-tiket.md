# ADR 0055: Kredensial PPP Dibuat di Langkah NOC dan Terlihat oleh Teknisi/NOC di Tiket

**Status**: Accepted

## Konteks
Aktivasi Pemasangan dan Proses NOC hanya men-generate PPP Username, tidak pernah PPP Password (kolom `ppp_password_terenkripsi` nullable sejak registrasi billing dibuat tanpa kredensial PPP). Akibatnya `MikrotikService::createOrUpdatePppoeSecret()` selalu menolak dengan "Password PPPoE ... tidak boleh kosong". Di sisi lain Teknisi butuh username dan password di halaman tiket untuk konfigurasi CPE, padahal aturan lama hanya mengizinkan `super_admin` mengungkap password.

## Keputusan
1. **Generate di langkah NOC**, bersama username, hanya bila password masih kosong dan layanan `PROSES` (Aktivasi Pemasangan, Proses NOC, tombol Provisi). Password tidak pernah ditimpa. Provisioning yang gagal mempertahankan password yang sudah dibuat.
2. **Tidak di dalam `createOrUpdatePppoeSecret()`**: job, listener, dan rekonsiliasi memanggilnya untuk layanan hidup; membuat password di sana akan menimpa secret nyata di router.
3. **Pengecualian input manual**: mode "Sudah Registrasi Mikrotik", dan Proses NOC pada layanan non-`PROSES` yang password-nya kosong. NOC mengetik password asli (wajib, maks 64, tanpa aturan format) karena UNMS tidak boleh mengarang password untuk secret yang sudah ada di router.
4. **Reveal di tiket** (`TicketPolicy::lihatKredensialPpp`): izin `layanan_pelanggan.lihat_ppp_password` diberikan ke `teknisi` dan `noc`; hanya berlaku pada tiket yang belum Selesai/Batal dan yang boleh dilihat user tsb (Teknisi: PIC). `super_admin` tetap di mana saja. Setiap reveal tercatat di Activitylog. Username selalu terlihat.

## Konsekuensi
- Permission baru dibagikan lewat data migration (seeder saja tidak menjangkau DB produksi).
- Layanan `PROSES` yang macet dengan password kosong sembuh lewat Provisi atau kirim ulang Proses NOC; layanan `Aktif` berpassword kosong tetap gagal keras sampai NOC mengisi password aslinya.
- Password terlihat oleh lebih banyak peran; batasannya adalah status tiket dan audit trail, bukan lagi peran tunggal.
