# PRD #1: Users & Roles Management

**Modul:** Users & Roles
**Urutan Implementasi:** 1 dari 7 (fondasi)
**Status:** Draft

---

## 1. Ringkasan

Modul ini mengelola akun staff internal (bukan pelanggan) beserta role/hak aksesnya. Ini adalah modul fondasi — hampir seluruh modul lain (Customers, Subscriptions, Tickets, Inventory) memiliki relasi ke `users` melalui kolom `created_by` atau `assigned_to`.

## 2. Tujuan

- Staff dapat login ke NMS Staff App dengan akun masing-masing (bukan akun bersama).
- Super admin dapat mengatur siapa yang punya akses ke modul apa, tanpa perlu ubah kode.
- Setiap aksi penting bisa ditelusuri ke user yang melakukannya (akuntabilitas).

## 3. Role & Akses Modul Ini

| Role | Akses |
|---|---|
| super_admin | Full CRUD user, assign/ubah role |
| admin, sales, noc, teknisi | Tidak bisa akses modul ini sama sekali (hanya bisa edit profil sendiri) |

## 4. User Stories

- Sebagai **super_admin**, saya ingin membuat akun staff baru dan menetapkan role-nya, supaya staff baru bisa langsung bekerja sesuai tanggung jawabnya.
- Sebagai **super_admin**, saya ingin menonaktifkan akun staff yang resign, tanpa menghapus datanya (karena riwayat aksi mereka masih perlu tercatat).
- Sebagai **staff mana pun**, saya ingin mengganti password saya sendiri.
- Sebagai **super_admin**, saya ingin melihat riwayat login staff untuk keperluan audit.

## 5. Functional Requirements

| ID | Requirement |
|---|---|
| FR-1.1 | Sistem menyediakan CRUD user: create, view (list + detail), edit, deactivate |
| FR-1.2 | Tidak ada hard delete user — hanya soft delete/deactivate (`status = inactive`) |
| FR-1.3 | Super admin dapat assign satu role per user dari 5 role yang ada (super_admin, admin, sales, noc, teknisi) |
| FR-1.4 | Form create/edit user memvalidasi email unik dan format valid |
| FR-1.5 | Password di-hash (bcrypt/argon2, default Laravel), minimal 8 karakter |
| FR-1.6 | User dapat mengganti password sendiri melalui halaman profil (butuh password lama sebagai konfirmasi) |
| FR-1.7 | Sistem mencatat `last_login_at` setiap kali user berhasil login |
| FR-1.8 | Super admin tidak dapat menonaktifkan/menghapus akunnya sendiri (mencegah lockout) |
| FR-1.9 | List user dapat difilter berdasarkan role dan status (active/inactive), serta dicari berdasarkan nama/email |

## 6. Data Model

**`users`**
| Kolom | Tipe | Keterangan |
|---|---|---|
| id | bigint PK | |
| name | string | |
| email | string, unique | |
| password | string | hashed |
| phone | string, nullable | |
| status | enum(active, inactive) | default: active |
| last_login_at | timestamp, nullable | |
| created_at, updated_at | timestamp | |

**`roles`** & **`model_has_roles`** — disediakan otomatis oleh package Spatie `laravel-permission`, tidak perlu tabel custom.

## 7. Business Rules & Validasi

- Email harus unik di seluruh sistem.
- User yang di-deactivate tidak bisa login (tampilkan pesan jelas, bukan generic "invalid credentials").
- Satu user hanya boleh punya satu role aktif dalam sistem ini (bukan multi-role), untuk menyederhanakan matriks permission.
- Perubahan role oleh super_admin harus tercatat di `audit_logs` (lihat dokumen desain utama bagian 5.2).

## 8. Halaman yang Dibutuhkan (UI)

1. Halaman list user (tabel + filter + search)
2. Form create user (termasuk pilih role)
3. Form edit user
4. Halaman detail user (opsional: tampilkan riwayat login/aktivitas)
5. Halaman "Profil Saya" (ganti nama, phone, password)

## 9. Out of Scope (untuk PRD ini)

- Multi-role per user
- Login via SSO/social login
- Two-factor authentication (bisa masuk fase hardening terpisah)
- Custom permission per-user di luar role (permission tetap by-role, bukan by-individual)

## 10. Dependencies

- Package: `spatie/laravel-permission`
- Tidak bergantung pada modul lain (ini modul pertama)

## 11. Acceptance Criteria

- [ ] Super admin bisa membuat user baru dengan role tertentu, dan user tersebut bisa login dengan role yang sesuai.
- [ ] User dengan role selain super_admin tidak bisa mengakses halaman manajemen user (redirect/403).
- [ ] User yang dinonaktifkan tidak bisa login.
- [ ] Super admin tidak bisa menonaktifkan akun sendiri.
- [ ] Setiap user bisa mengganti password sendiri dengan validasi password lama.