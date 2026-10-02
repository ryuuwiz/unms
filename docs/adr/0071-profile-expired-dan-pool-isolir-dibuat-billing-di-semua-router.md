# ADR 0071: Profile `EXPIRED` dan Pool Isolir Dibuat Billing di Semua Router

**Status**: Accepted — menggantikan bagian ADR-0063 tentang profile `ISOLIR` dan IP Pool Isolir yang dipilih admin.

Isolir tetap memindahkan secret ke satu profile (bukan disable), tetapi profile itu kini bernama `EXPIRED` dan billing sendiri yang membuat pool serta profile-nya di setiap router dari satu subnet isolir yang sama (default `172.30.0.0/16`, rate-limit `256k/256k`, keduanya di config). Alasannya: dengan pool pilihan admin, router yang lupa disetel tidak bisa mengisolir sama sekali, dan subnet yang seragam membuat firewall redirect NOC sama di semua router.

## Considered Options

- **Tetap pilih IP Pool Isolir per router (ADR-0063)** — ditolak: bergantung pada admin, router baru tidak bisa mengisolir sampai disetel.
- **Profile berbeda per alasan suspend (`EXPIRED` vs `ISOLIR`)** — ditolak: tidak ada perbedaan perilaku, hanya menambah pool kedua per router.
- **Subnet/rate-limit diatur di UI** — ditolak: mengubahnya berarti migrasi semua router, jadi cukup config.

## Consequences

- Subnet isolir yang sudah dipakai objek NOC di sebuah router tidak ditimpa: provisioning `EXPIRED` di router itu gagal dan NOC diberi tahu.
- Rekonsiliasi memindahkan secret Suspend dari `ISOLIR` lama ke `EXPIRED`, lalu menghapus profile `ISOLIR` yang tidak lagi dipakai. Pool isolir lama dibiarkan di router.
- "Isolir" tetap nama tindakannya dan status layanan tetap `Suspend`; `EXPIRED` hanya nama profile di router, berbeda dari status Pelanggan `Expired`.
