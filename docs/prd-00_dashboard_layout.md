# PRD #0: Dashboard Shell & UI Foundation

**Modul:** Dashboard Shell (Layout, Navigasi, Komponen UI Reusable)
**Urutan Implementasi:** 0 dari 7 — dibangun sebelum PRD #1
**Status:** Draft
**Depends on:** —

---

## 1. Ringkasan

Sebelum modul fungsional (Users, Customers, Packages, dst.) dibangun, perlu ada **kerangka dasar tampilan** yang konsisten: layout utama, sidebar navigasi per role, dan kumpulan komponen Livewire reusable (tabel, form, modal, alert). Tanpa ini, tiap PRD berikutnya akan membangun UI-nya sendiri-sendiri dan hasilnya tidak konsisten serta banyak kode duplikat.

Modul ini **tidak punya fitur bisnis** — murni fondasi teknis/UI yang dipakai semua modul lain.

## 2. Tujuan

- Semua halaman CRUD di modul berikutnya (PRD #1–#7) punya tampilan konsisten tanpa perlu membangun layout dari nol tiap kali.
- Navigasi sidebar otomatis menyesuaikan menu berdasarkan role user yang login (tidak menampilkan menu yang tidak bisa diakses).
- Komponen UI dasar (tabel, form, modal, notifikasi) dibuat sekali sebagai Livewire component/Blade component, dipakai berulang di semua modul.
- Developer berikutnya (atau diri sendiri di masa depan) bisa langsung pakai komponen ini tanpa menebak-nebak konvensi styling.

## 3. Role & Akses Modul Ini

Modul ini bukan modul dengan data bisnis, sehingga tidak ada RBAC atas "data" — tapi **navigasi menu** yang ditampilkan wajib mengikuti role user (lihat FR-0.3).

## 4. User Stories

- Sebagai **staff mana pun**, saya ingin melihat sidebar menu yang hanya berisi modul yang bisa saya akses sesuai role saya, supaya tidak bingung/klik menu yang ujungnya 403.
- Sebagai **developer**, saya ingin memakai komponen tabel/form/modal yang sudah jadi, supaya membangun PRD #1–#7 lebih cepat dan konsisten.
- Sebagai **staff**, saya ingin mendapat notifikasi sukses/gagal (toast) yang konsisten setiap kali melakukan aksi (create/update/delete) di modul mana pun.
- Sebagai **staff**, saya ingin tampilan dashboard tetap rapi dan terbaca di layar laptop maupun tablet (responsive dasar, tidak perlu optimasi mobile penuh untuk staff app).

## 5. Functional Requirements

| ID | Requirement |
|---|---|
| FR-0.1 | Tersedia 1 layout utama (`layouts.app`) berisi: topbar (nama user login, tombol logout), sidebar navigasi, area konten utama |
| FR-0.2 | Sidebar menu didefinisikan terpusat (mis. `config/menu.php` atau sejenis), tiap item menu punya atribut permission yang dicek sebelum ditampilkan |
| FR-0.3 | Menu yang tidak sesuai permission/role user yang login otomatis disembunyikan (bukan ditampilkan lalu 403 saat diklik) |
| FR-0.4 | Tersedia Livewire component tabel generik/reusable: mendukung kolom dinamis, search, pagination, sorting — dipakai ulang oleh list Users, Customers, Packages, dst. |
| FR-0.5 | Tersedia Blade/Livewire component form generik untuk elemen umum: text input, select, textarea, date picker — dengan style Tailwind konsisten dan tampilan error validasi seragam |
| FR-0.6 | Tersedia component modal (untuk konfirmasi hapus/nonaktifkan, dan form create/edit ringan di dalam modal) |
| FR-0.7 | Tersedia sistem notifikasi toast (sukses/error/warning) yang dipanggil dari Livewire component mana pun dengan cara seragam (mis. `$this->dispatch('notify', ...)`) |
| FR-0.8 | Tersedia halaman/komponen empty state (saat data kosong) dan loading state (saat Livewire memproses) yang konsisten |
| FR-0.9 | Breadcrumb otomatis mengikuti halaman aktif |
| FR-0.10 | Halaman login terpisah dari layout dashboard (layout sendiri, minimalis) |
| FR-0.11 | Tema warna, font, dan spacing didefinisikan di `tailwind.config.js` sebagai design token (bukan warna hardcode di tiap Blade), supaya rebranding di masa depan tidak perlu ubah tiap file |

## 6. Data Model

Tidak ada tabel database baru di PRD ini — modul ini murni asset frontend (Blade views, Livewire components, config file menu).

## 7. Business Rules & Validasi

- Menu sidebar **wajib** dicek terhadap permission Spatie (bukan hardcode per role), supaya saat permission berubah di masa depan, menu ikut menyesuaikan otomatis tanpa ubah kode.
- Semua komponen form baru di modul berikutnya wajib memakai component dari PRD ini (bukan menulis input HTML mentah), untuk menjaga konsistensi visual.
- Warna/style tidak boleh di-hardcode inline di Blade view modul bisnis — harus lewat class Tailwind yang merujuk ke token di `tailwind.config.js`.

## 8. Halaman/Komponen yang Dibutuhkan

1. `layouts.app` — layout utama dashboard
2. `layouts.guest` — layout untuk halaman login
3. Komponen: `<x-table>`, `<x-form-input>`, `<x-form-select>`, `<x-modal>`, `<x-toast>`, `<x-empty-state>`, `<x-breadcrumb>`
4. Halaman login
5. Halaman dashboard kosong (landing setelah login, sebelum modul lain terisi konten — cukup ucapan selamat datang + placeholder widget)

## 9. Out of Scope (untuk PRD ini)

- Widget/statistik dashboard yang berisi data bisnis sungguhan (mis. jumlah pelanggan aktif) — itu ditambahkan belakangan setelah modul terkait ada datanya
- Dark mode
- Real-time update (Laravel Reverb) — menyusul di fase lanjutan jika diperlukan
- Aplikasi customer portal (ini khusus untuk NMS Staff App)

## 10. Dependencies

- Tidak bergantung modul lain — ini dasar untuk semua PRD berikutnya (PRD #1–#7 bergantung pada PRD #0)
- Perlu Spatie `laravel-permission` sudah ter-install untuk pengecekan menu (FR-0.3), meski data role/user sungguhan baru diisi di PRD #1

## 11. Acceptance Criteria

- [ ] Login menampilkan layout terpisah dari dashboard, redirect ke dashboard setelah sukses.
- [ ] Sidebar menu berbeda tampil sesuai role user yang login (uji dengan minimal 2 role berbeda).
- [ ] Component tabel reusable berhasil dipakai untuk menampilkan data dummy dengan search & pagination berfungsi.
- [ ] Notifikasi toast muncul konsisten setelah aksi create/update/delete pada data dummy.
- [ ] Tidak ada warna/style Tailwind hardcode di luar `tailwind.config.js` untuk elemen tema utama (dicek lewat code review).
- [ ] Tampilan dashboard tetap rapi di lebar layar 1280px dan 768px (uji manual resize browser).