# Tenggat Pembayaran Invoice Pertama Dicek Terpisah dari Isolir Bulanan

Sebelum perubahan ini, layanan baru yang tidak pernah membayar invoice pertamanya tetap `Aktif` (internet tetap nyala) sampai `tanggal_expired` penuh (mis. 1 bulan) tercapai, karena `layanan:cek-isolir` hanya membaca `tanggal_expired`, bukan status invoice. Kami memilih menambah command terpisah (`layanan:cek-tunggakan-pertama`, jadwal per-jam) yang secara sempit mengisolir layanan `Aktif` dengan invoice pertama (`periode_tagihan` NULL) yang masih terbuka melewati H+1 dari tanggal mulai — alih-alih menggeneralisasi pemicu isolir bulanan menjadi "invoice apa pun yang kadaluarsa".

Alasan: mekanisme isolir bulanan berbasis `tanggal_expired` + Siklus Tagihan sudah stabil dan teruji; menggantinya berisiko regresi pada alur yang sudah berjalan baik untuk kasus yang tidak diminta. Command terpisah ini murni aditif dan memanggil `UbahStatusLayananAction` yang sama, jadi realtime disable PPP-nya identik dengan isolir bulanan biasa.

Konsekuensi: ada dua jalur command yang bisa men-suspend sebuah layanan (`layanan:cek-isolir` harian berbasis `tanggal_expired`, dan `layanan:cek-tunggakan-pertama` per-jam berbasis invoice pertama) — keduanya idempoten dan aman berjalan bersamaan karena sama-sama lewat `UbahStatusLayananAction`.
