# ADR 0029: Pemisahan Seeder Master Data vs Mock Development dan Perintah Setup Instalasi Produksi (app:install)

## Konteks
Sebelumnya, `RolesAndPermissionsSeeder.php` mendaftarkan permission/role sekaligus membuat 5 akun staf uji coba (dummy test users) dengan kredensial default (`password`). Selain itu, `DatabaseSeeder.php` menjalankan seluruh seeder bisnis (pelanggan fiktif, tiket dummy, router simulasi, dll). Apabila `db:seed` dieksekusi di lingkungan *production*, sistem berisiko kemasukan data dummy dan akun default dengan kata sandi lemah.

Untuk memastikan kesiapan deployment produksi yang aman, modular, dan terotomasi, diperlukan pemisahan yang tegas antara master data esensial produksi dengan data mock lokal, serta penyediaan perintah instalasi produksi interaktif dan non-interaktif (*CI/CD friendly*).

## Keputusan yang Diambil

1. **Pemisahan Seeder Bersih (Clean Seeder Segregation)**:
   - `RolesAndPermissionsSeeder.php`: Dikhususkan secara murni dan idempoten hanya untuk mendaftarkan definisi *Permissions* dan *Roles* Spatie beserta pemetaannya, tanpa membuat akun pengguna fiktif.
   - `DevUsersSeeder.php`: Memuat pembuatan 5 akun pengguna dummy (`superadmin@example.com`, `admin@example.com`, `sales@example.com`, `noc@example.com`, `teknisi@example.com`) khusus untuk kebutuhan pengembangan lokal dan testing.
   - `ProductionSeeder.php`: Bundel seeder data master esensial produksi yang mencakup:
     - `RolesAndPermissionsSeeder` (RBAC)
     - `PerusahaanSeeder` (Profil Perusahaan Default)
     - `WaTemplateSeeder` (Template Pesan WhatsApp Operasional)
     - `AturanPengingatTagihanSeeder` (Aturan Otomasi Pengingat Jatuh Tempo)

2. **Perintah Setup Produksi Otomatis (`php artisan app:install`)**:
   - Menyediakan perintah artisan `app:install` (dengan alias `app:setup-production`).
   - Mendukung dua mode operasi:
     - **Mode Interaktif Wizard**: Meminta input Nama, Email valid, Password bertopeng (min 8 karakter dengan konfirmasi), dan nomor telepon/WhatsApp.
     - **Mode Non-Interaktif / Headless (`--force`)**: Menerima opsi `--name=`, `--email=`, `--password=`, `--phone=`, `--skip-migrate`, `--skip-storage-link`. Jika password tidak disertakan pada user baru, sistem menghasilkan kata sandi acak aman 16 karakter secara otomatis via `Str::password(16)` dan menampilkannya pada tabel ringkasan.
   - Menjamin idempoten terhadap akun yang sudah ada: Menyelaraskan peran `super_admin`, mengaktifkan status user, dan memperbarui password jika diinstruksikan tanpa merusak integritas audit log.
   - Mengosongkan cache permission Spatie secara otomatis pasca eksekusi.

## Konsekuensi
- Lingkungan produksi dapat di-provisioning dengan satu baris perintah tanpa risiko kebocoran akun mock atau kata sandi default publik.
- Script deployment (CI/CD, Docker entrypoint, Forge script) dapat menjalankan `php artisan app:install --force --email=admin@domain.com --password=...` secara zero-touch.
- Pengembang lokal tetap dapat menjalankan `php artisan migrate --seed` melalui `DatabaseSeeder` yang menyertakan `DevUsersSeeder`.
