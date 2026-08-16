# PRD #2: Customer Management

**Modul:** Customers
**Urutan Implementasi:** 2 dari 7
**Status:** Draft
**Depends on:** PRD #1 (Users & Roles)

---

## 1. Ringkasan

Modul ini mengelola data master pelanggan ISP: identitas, kontak, dan alamat instalasi. Belum mencakup subscription/langganan atau billing — murni data pelanggan.

## 2. Tujuan

- Sales/admin dapat mendaftarkan calon pelanggan baru dengan cepat dan lengkap.
- Data pelanggan menjadi satu sumber kebenaran (single source of truth) yang dipakai modul Subscriptions, Tickets, dan Billing nantinya.
- Nomor WA pelanggan tervalidasi sejak awal, karena akan dipakai untuk notifikasi otomatis di fase berikutnya.

## 3. Role & Akses Modul Ini

| Role | Akses |
|---|---|
| super_admin | Full CRUD |
| admin | Full CRUD |
| sales | Create + view (tidak bisa edit/hapus data pelanggan yang dibuat orang lain) |
| noc, teknisi | View only (butuh lihat data pelanggan saat menangani tiket) |

## 4. User Stories

- Sebagai **sales**, saya ingin mendaftarkan pelanggan baru dengan data kontak dan alamat instalasi, supaya proses onboarding tercatat sejak awal.
- Sebagai **admin**, saya ingin mencari pelanggan berdasarkan nama/nomor HP/kode pelanggan dengan cepat.
- Sebagai **teknisi**, saya ingin melihat alamat & titik koordinat instalasi pelanggan saat akan survei/instalasi.
- Sebagai **admin**, saya ingin menonaktifkan pelanggan yang berhenti berlangganan tanpa menghapus riwayat datanya.

## 5. Functional Requirements

| ID | Requirement |
|---|---|
| FR-2.1 | Sistem generate `customer_code` otomatis dan unik saat create (format disarankan: `CUST-000001`) |
| FR-2.2 | CRUD data pelanggan: nama, email (opsional), no. HP/WA (wajib), alamat, alamat instalasi, koordinat (lat/lng) |
| FR-2.3 | Nomor HP/WA divalidasi format Indonesia (`08xx` atau `+62xx`) |
| FR-2.4 | Status pelanggan: `active`, `inactive` — soft delete, bukan hard delete |
| FR-2.5 | List pelanggan mendukung pencarian (nama, no. HP, customer_code) dan filter status |
| FR-2.6 | Sistem mencatat `created_by` (user yang input data) otomatis |
| FR-2.7 | Sales hanya bisa mengedit data pelanggan yang dia buat sendiri; admin/super_admin bisa edit semua |
| FR-2.8 | Input koordinat mendukung input manual (lat/lng) atau pilih titik di peta (map picker) |

## 6. Data Model

**`customers`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| customer_code | string, unique | auto-generated |
| name | string | |
| email | string, nullable | |
| phone | string | wajib, format WA valid |
| address | text, nullable | alamat domisili |
| installation_address | text | alamat pemasangan |
| lat, lng | decimal, nullable | |
| status | enum(active, inactive) | default: active |
| created_by | bigint FK → users.id | |
| created_at, updated_at | timestamp | |

## 7. Business Rules & Validasi

- Nomor HP wajib diisi dan tervalidasi (dipakai untuk WA notifikasi di fase berikutnya — jangan biarkan data kotor masuk sejak awal).
- `customer_code` tidak boleh diubah setelah dibuat.
- Pelanggan dengan subscription aktif (fase berikutnya) tidak boleh di-deactivate langsung — perlu terminasi subscription dulu (aturan ini di-enforce nanti saat modul Subscriptions ada, dicatat sebagai dependency).
- Sales hanya melihat & edit data yang dia buat sendiri di tampilan default (bisa lihat semua tapi read-only untuk milik sales lain).

## 8. Halaman yang Dibutuhkan (UI)

1. List pelanggan (tabel, search, filter status)
2. Form create pelanggan (dengan map picker untuk koordinat)
3. Form edit pelanggan
4. Halaman detail pelanggan (menyisakan tab kosong untuk "Subscriptions" dan "Tickets" yang akan diisi modul berikutnya)

## 9. Out of Scope (untuk PRD ini)

- Subscription/langganan pelanggan (PRD #5)
- Billing/invoice (fase Xendit terpisah)
- Import massal (bulk import CSV) — bisa jadi enhancement terpisah

## 10. Dependencies

- PRD #1 (Users & Roles) — untuk `created_by` dan RBAC

## 11. Acceptance Criteria

- [ ] Sales bisa mendaftarkan pelanggan baru, `customer_code` ter-generate otomatis dan unik.
- [ ] Sales tidak bisa mengedit data pelanggan yang dibuat sales lain.
- [ ] NOC/teknisi bisa melihat detail pelanggan tapi tidak ada tombol edit/hapus.
- [ ] Nomor HP dengan format tidak valid ditolak saat submit form.
- [ ] Pencarian pelanggan berdasarkan nama, no. HP, atau customer_code berfungsi.