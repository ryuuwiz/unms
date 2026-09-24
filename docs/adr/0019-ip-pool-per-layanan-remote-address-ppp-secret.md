# ADR 0019: IP Pool per Layanan Pelanggan sebagai Remote-Address PPP Secret

**Status**: Superseded oleh ADR-0051 (perilaku `local/remote-address` PPP Secret; isolasi jalur/segmentasi tetap berlaku)  
**Date**: 2026-08-24

## Konteks

PPP Secret di MikroTik RouterOS memiliki field `remote-address` yang menentukan alokasi IP pelanggan saat koneksi PPPoE terbentuk. Tanpa `remote-address` yang eksplisit, MikroTik bergantung pada konfigurasi di level PPP Profile — yang bersifat global per profil bandwidth dan tidak membedakan router maupun subnet.

Masalah yang ditemukan di produksi:
1. **Tabrakan IP** — Pelanggan yang terhubung ke router berbeda dengan IP Pool yang berbeda mendapat IP dari pool yang salah karena `remote-address` kosong di PPP Secret.
2. **Duplikat PPP Secret lintas router** — Ketika layanan dipindahkan ke router lain via Edit form, secret lama di router lama tidak dihapus, menghasilkan secret duplikat.
3. **Rekonsiliasi tidak mendeteksi mismatch remote-address** — `autoRecoverPppSecrets()` hanya memeriksa keberadaan secret dan kecocokan profile, bukan `remote-address`.

## Keputusan

### 1. Kolom `ip_pool_id` di `layanan_pelanggan`

Setiap `LayananPelanggan` memiliki FK eksplisit ke `ip_pools.id` yang menentukan pool mana yang digunakan sebagai `remote-address` di PPP Secret MikroTik. Ini menjamin UNMS sebagai satu-satunya sumber kebenaran alokasi IP.

Kolom `nullable` agar kompatibel dengan data historis. Data lama di-migrate otomatis jika router punya tepat 1 pool.

### 2. Helper `resolveRemoteAddress()` di model

```php
public function resolveRemoteAddress(): ?string
{
    if (! empty($this->ip_static)) {
        return $this->ip_static;  // IP statis selalu menang
    }
    return $this->ipPool?->nama_pool ?? null;  // Fallback ke nama pool
}
```

Prioritas: `ip_static` (jika ada) → `ip_pool.nama_pool` → null.

### 3. `createOrUpdatePppoeSecret()` selalu mengirim `remote-address`

Menggantikan logika conditional lama yang hanya mengirim `remote-address` untuk IP statis. Sekarang `resolveRemoteAddress()` dipanggil dan hasilnya selalu dikirim ke RouterOS jika tidak null.

### 4. `autoRecoverPppSecrets()` cek mismatch `remote-address`

Kondisi `needsRecovery = true` ditambahkan jika `remote['remote-address']` di RouterOS berbeda dengan `resolveRemoteAddress()` dari UNMS.

### 5. `LayananPelangganObserver` + `CleanupPppSecretOnOldRouterJob`

Ketika `router_id` berubah pada `updating()`, Observer dispatch job ke queue `mikrotik` untuk menghapus secret dari router lama. Jika router lama offline, kegagalan dicatat di `MikrotikJobLog` sebagai Failed (tidak di-throw). `ip_pool_id` di-reset ke null saat router berubah karena pool milik router lama tidak valid di router baru.

### 6. Aturan Validasi & Form UX (Create & Edit)

- **Validasi Kondisional**:
  - `ip_pool_id` wajib diisi jika `jenis_koneksi === 'pppoe'`.
  - `ip_static` wajib diisi dan berformat IPv4 valid jika `jenis_koneksi === 'ip_static'`.
- **Auto-Select Single Router Gateway & Single IP Pool**:
  - Saat form Create diinisialisasi atau masuk ke Step 2, jika sistem hanya memiliki tepat 1 Router Online aktif, Livewire secara otomatis memilih router tersebut (`initSingleRouterSelection()`).
  - Selanjutnya, jika router tersebut hanya memiliki tepat 1 IP Pool aktif, sistem secara otomatis memilih pool tersebut (`updatedRouterId()`).
  - Jika router memiliki 0 IP Pool, form menampilkan callout peringatan informatif untuk membuat IP Pool terlebih dahulu atau menggunakan opsi IP Statis.
  - Dropdown Router Gateway dan IP Pool menggunakan opsi placeholder eksplisit `-- Pilih Router Gateway --` dan `-- Pilih IP Pool --` dengan mode `searchable` serta `wire:model.live` untuk mencegah visual desync di browser Chromium.
- **Lokalisasi Pesan Validasi**:
  - Seluruh pesan validasi menggunakan bahasa Indonesia baku yang terpusat di `lang/id/validation.php` dan pesan kustom pada komponen Livewire.

## Alternatif yang Ditolak

**Pool per PPP Profile** — MikroTik mendukung `remote-address` di level profile. Ditolak karena satu profil digunakan oleh banyak layanan di banyak router; pool per-router tidak dapat dikonfigurasikan di level profile tanpa duplikasi profil.

**Pool pertama/terbesar dari router** — Auto-select pool tanpa keputusan eksplisit. Ditolak karena ambigu jika router punya >1 pool dengan subnet berbeda (misalnya pool residensial vs bisnis).

## Konsekuensi

- Staf harus memilih IP Pool saat mendaftarkan layanan baru (dropdown difilter per router, otomatis terpilih jika single pool).
- Layanan existing dengan router single-pool ter-migrate otomatis; router multi-pool perlu diisi manual.
- `resolveRemoteAddress()` menjadi single source of truth untuk `remote-address` di seluruh codebase.
- Form Create & Edit mendukung pendaftaran layanan baik mode PPPoE (IP Pool) maupun IP Statis dedicated.

