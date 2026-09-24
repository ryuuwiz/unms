# ADR 0051: Profile PPP per Pool, Secret Dinamis Tanpa Alamat, Alamat Sesi Dibaca Live

**Status**: Accepted — menggantikan ADR-0019, 0022, 0033, 0044.

## Konteks
ADR-0044 membuat UNMS mengalokasikan IP literal sendiri (`ip_dynamic`, `allocateDynamicIp()`) karena `/ppp/secret` menolak nama pool di `remote-address`, dan menolak opsi pool di PPP Profile sebab satu profile per profil bandwidth dipakai bersama semua pool router. Akibatnya UNMS menjadi pemegang state alokasi IP yang sebenarnya dimiliki RouterOS: bisa drift terhadap `/ip/pool/used` dan `/ppp/active`, dan nilai `local/remote-address` harus terus direkonsiliasi ke setiap secret.

## Keputusan
1. PPP Profile dibuat **per kombinasi Profil Bandwidth × IP Pool**: `{nama_bandwidth}@{nama_pool}`, membawa `rate-limit`, `local-address` (gateway pool), dan `remote-address` (nama pool — valid di `/ppp/profile`). Keberatan ADR-0044 terjawab karena profile tidak lagi dibagi lintas pool.
2. PPPoE dinamis: `local-address`/`remote-address` di `/ppp/secret` **dikosongkan**; RouterOS mengalokasikan IP dari pool profile. `IP Static` dan IP Publik Dedicated tetap literal di secret dengan profile polos `{nama_bandwidth}`.
3. UNMS tidak lagi mengalokasikan atau menulis `ip_dynamic` (kolom dibiarkan, tidak di-drop). IP sesi dibaca live dari `/ppp/active`; pemakaian pool dari `/ip/pool/used` (`MikrotikService::getPoolUsage()`, satu query per router, cache singkat, tidak pernah throw).
4. Rekonsiliasi menganggap profile lama polos dan alamat literal pada secret dinamis sebagai drift dan memperbaikinya (dry-run tersedia). Sesi yang sedang aktif tidak diputus; perubahan berlaku saat reconnect.

## Konsekuensi
- Fallback otomatis ke pool lain saat pool penuh dihapus. Pool penuh kini muncul sebagai kegagalan login di router, bukan galat provisi di UNMS; mitigasinya indikator pemakaian di halaman IP Pool.
- Jumlah profile di router naik (profil × pool).
- Alamat di IP Pool tidak boleh berisi IP Publik/`ip_static` router yang sama (divalidasi di form) agar pool RouterOS tidak membagikannya ke pelanggan lain.
- Perilaku RouterOS (nama profile berisi `@`, pool via profile, `/ip/pool/used`) diuji lewat mock di test; harus diverifikasi manual di router staging sebelum rollout.
