# PRD #3: Package Management

**Modul:** Packages (Paket Internet)
**Urutan Implementasi:** 3 dari 7
**Status:** Draft
**Depends on:** PRD #1 (Users & Roles)

---

## 1. Ringkasan

Modul ini mengelola daftar paket internet yang ditawarkan ISP (kecepatan, harga, FUP). Paket ini akan dipakai oleh modul Subscriptions (PRD #5) dan nantinya di-mapping ke PPPoE profile Mikrotik saat fase integrasi Mikrotik.

## 2. Tujuan

- Admin dapat mengatur katalog paket internet tanpa perlu bantuan developer.
- Struktur data paket sudah menyediakan tempat untuk mapping ke Mikrotik profile di fase berikutnya, tanpa perlu migrasi ulang skema.

## 3. Role & Akses Modul Ini

| Role | Akses |
|---|---|
| super_admin | Full CRUD |
| admin | Full CRUD |
| sales, noc, teknisi | View only |

## 4. User Stories

- Sebagai **admin**, saya ingin menambah paket internet baru (misal "Home 20 Mbps") lengkap dengan harga, supaya sales bisa langsung menawarkan ke pelanggan.
- Sebagai **admin**, saya ingin menonaktifkan paket lama yang sudah tidak dijual, tanpa menghapusnya (karena masih dipakai pelanggan existing).
- Sebagai **sales**, saya ingin melihat daftar paket aktif beserta harga saat menawarkan ke calon pelanggan.

## 5. Functional Requirements

| ID | Requirement |
|---|---|
| FR-3.1 | CRUD paket: nama, kecepatan (Mbps), harga, kuota FUP (opsional), status aktif/nonaktif |
| FR-3.2 | Paket yang sudah dipakai oleh subscription (fase berikutnya) tidak bisa dihapus, hanya bisa dinonaktifkan |
| FR-3.3 | Harga divalidasi harus lebih besar dari 0 |
| FR-3.4 | List paket menampilkan status aktif/nonaktif dengan jelas, default hanya tampilkan yang aktif |
| FR-3.5 | Sistem menyediakan kolom relasi kosong (placeholder) untuk mapping ke Mikrotik PPPoE profile — field ini boleh kosong di modul ini, diisi saat fase integrasi Mikrotik |

## 6. Data Model

**`packages`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | mis. "Home 20 Mbps" |
| speed_mbps | integer | |
| price | decimal(12,2) | |
| fup_quota_gb | integer, nullable | null = unlimited |
| is_active | boolean | default: true |
| created_at, updated_at | timestamp | |

> Tabel `mikrotik_profile_mappings` (relasi package ↔ router ↔ pppoe_profile_name) **belum dibuat di PRD ini** — akan dirancang detail di PRD fase integrasi Mikrotik, supaya PRD ini fokus hanya pada data paket murni.

## 7. Business Rules & Validasi

- Nama paket unik (mencegah duplikasi paket dengan nama sama).
- Paket tidak bisa dihapus jika sudah direferensikan subscription — cek dependency ini di modul Subscriptions.
- Menonaktifkan paket tidak mempengaruhi pelanggan yang sudah berlangganan paket tersebut (mereka tetap jalan, hanya tidak bisa dipilih untuk pelanggan baru).

## 8. Halaman yang Dibutuhkan (UI)

1. List paket (tabel + toggle status aktif/nonaktif)
2. Form create/edit paket

## 9. Out of Scope (untuk PRD ini)

- Mapping ke PPPoE profile Mikrotik (fase integrasi Mikrotik)
- Diskon/promo paket
- Paket bundling (mis. internet + TV kabel)

## 10. Dependencies

- PRD #1 (Users & Roles) — untuk RBAC

## 11. Acceptance Criteria

- [ ] Admin bisa membuat paket baru dan langsung muncul di list dengan status aktif.
- [ ] Admin bisa menonaktifkan paket, dan paket tersebut tidak muncul di dropdown pemilihan paket untuk pelanggan baru (di modul Subscriptions nanti).
- [ ] Sales tidak melihat tombol create/edit/hapus paket.
- [ ] Percobaan membuat paket dengan harga 0 atau negatif ditolak sistem.