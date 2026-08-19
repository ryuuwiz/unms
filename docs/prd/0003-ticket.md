# Panduan Implementasi Fase 4: Modul Ticket Lengkap dengan Histori

**Referensi**: PRD-Arsitektur-UNMS-Laravel.md Bagian 2 (Role & Permission), Bagian 3.7 (skema `ticket`/`ticket_histori`)
**Status proyek**: Fase 3 (Xendit) sedang testing — modul ini dikerjakan paralel/berikutnya, tidak bergantung pada Fase 3 selesai.

---

## Bagian 1 — Urutan Kerja

```
1. Migrasi & model (termasuk enum PHP native)
2. State machine status + matrix transisi per role
3. Action class terpusat untuk perubahan status (single source of truth)
4. Histori otomatis tercatat lewat action class di atas
5. Notifikasi internal (database notification, bukan WA)
6. CRUD & assignment PIC per jenis ticket
7. Dashboard/list terfilter per divisi
8. Testing
```

Kenapa state machine + action class dikerjakan **sebelum** CRUD/UI: supaya begitu form ticket dibangun, satu-satunya jalan mengubah status adalah lewat action yang sudah tervalidasi — bukan `$ticket->update(['status' => $request->status])` langsung di controller yang gampang lolos tanpa validasi transisi atau lupa catat histori.

---

## Bagian 2 — Skema Detail (Pelengkap Bagian 3.7 PRD Arsitektur)

Tambahan dari skema dasar yang sudah dirancang, untuk kelengkapan operasional:

```
ticket (tambahan kolom)
  nomor_ticket (unik, format: TCK-{tahun}-{6 digit}, mis. TCK-2026-000123)
  sumber (enum: manual, sistem — 'sistem' untuk fase depan saat monitoring otomatis mendeteksi gangguan)
  sla_target_selesai (nullable, dihitung dari prioritas saat ticket dibuat)

ticket_histori (tambahan kolom)
  status_lama (nullable — null khusus untuk histori pertama saat ticket dibuat)
  status_baru (sudah ada di skema awal)
```

**Instruksi**: `nomor_ticket` di-generate di model event `creating()`, bukan di controller — supaya konsisten di manapun ticket dibuat (form manual, command, job otomatis di fase depan).

---

## Bagian 3 — State Machine & Matrix Transisi

### 3.1 Diagram status

```
baru → diproses → menunggu_konfirmasi → selesai
  ↓         ↓
  └────→ batal
```

- `menunggu_konfirmasi` khusus dipakai saat teknisi/noc sudah menyelesaikan pekerjaan lapangan tapi butuh approval admin/PIC senior sebelum resmi `selesai` (mis. verifikasi pemasangan benar sebelum invoice pertama diterbitkan).
- `batal` hanya bisa dicapai dari `baru` atau `diproses` — ticket yang sudah `menunggu_konfirmasi` tidak boleh langsung dibatalkan tanpa diturunkan ke `diproses` dulu (mencegah kerja lapangan yang sudah dilakukan hilang tanpa jejak).

### 3.2 Matrix siapa boleh transisi apa

| Dari → Ke | super_admin | admin | sales | noc | teknisi |
|---|---|---|---|---|---|
| baru → diproses | ✅ | ✅ | - | ✅ (jenis gangguan) | ✅ (hanya ticket yg di-assign ke dirinya) |
| diproses → menunggu_konfirmasi | ✅ | ✅ | - | ✅ | ✅ (hanya assigned) |
| menunggu_konfirmasi → selesai | ✅ | ✅ | - | ✅ (jenis gangguan) | - |
| baru/diproses → batal | ✅ | ✅ | ✅ (ticket yg dia buat sendiri) | ✅ (jenis gangguan) | - |
| assign/reassign PIC | ✅ | ✅ | - | ✅ (divisi noc/teknisi) | - |

**Aturan tambahan yang wajib ditegakkan di action class, bukan cuma di UI**:
- `teknisi` hanya boleh mengubah status ticket yang `pic_id` miliknya sendiri — validasi ini di action class, bukan cuma menyembunyikan tombol di UI (kalau hanya disembunyikan di UI, endpoint API tetap bisa dipanggil langsung).
- `sales` hanya boleh membatalkan ticket yang dia buat sendiri (`dibuat_oleh` = dirinya), belum lunas/belum diproses.

---

## Bagian 4 — Instruksi Implementasi: Enum & Model

### 4.1 Enum native PHP 8.4 (backed enum, bukan string constant)

Buat 4 enum:
```
App\Enums\Ticket\JenisTicket        : Pemasangan, Pencabutan, Gangguan, PindahAlamat
App\Enums\Ticket\PrioritasTicket    : Rendah, Sedang, Tinggi, Darurat
App\Enums\Ticket\DivisiTicket       : Admin, CustomerService, Sales, Noc, Teknisi
App\Enums\Ticket\StatusTicket       : Baru, Diproses, MenungguKonfirmasi, Selesai, Batal
```
Tiap enum backed `string` (bukan `int`) supaya nilai di database tetap terbaca manusiawi. Tambahkan method `label()` di tiap enum untuk tampilan UI (mis. `MenungguKonfirmasi::label()` → `"Menunggu Konfirmasi"`), dan method `StatusTicket::transisiValid(): array` yang mengembalikan daftar status tujuan yang sah dari status saat ini (implementasi matrix Bagian 3.1 langsung di enum, satu tempat rujukan).

Cast di model `Ticket`: `'jenis' => JenisTicket::class, 'prioritas' => PrioritasTicket::class, 'status' => StatusTicket::class`.

### 4.2 Model `Ticket`

- Relasi: `pelanggan()`, `layananPelanggan()` (nullable), `pic()` → `belongsTo(Pengguna::class, 'pic_id')`, `histori()` → `hasMany(TicketHistori::class)->latest()`, `dibuatOleh()`.
- Event `creating()`: generate `nomor_ticket`, hitung `sla_target_selesai` dari `prioritas` (mis. darurat = +4 jam, tinggi = +1 hari, sedang = +3 hari, rendah = +7 hari — sesuaikan dengan SLA operasional Anda).
- **Jangan** taruh logic perubahan status di model (`$ticket->tandaiSelesai()` dsb dengan side-effect kompleks) — pusatkan di action class (Bagian 5) supaya satu jalur, gampang diuji terisolasi.

### 4.3 Model `TicketHistori`

- Relasi: `ticket()`, `olehPengguna()`.
- **Read-only setelah dibuat** — jangan sediakan method update; histori adalah log, bukan data yang diedit.

---

## Bagian 5 — Action Class: Satu-satunya Jalan Ubah Status

### `App\Actions\Ticket\UbahStatusTicketAction`

Method `execute(Ticket $ticket, StatusTicket $statusBaru, Pengguna $pengguna, ?string $catatan = null): Ticket`:

1. **Validasi transisi**: cek `$statusBaru` ada di `$ticket->status->transisiValid()`. Jika tidak → lempar `TransisiStatusTidakValidException`.
2. **Validasi otorisasi**: cek kombinasi role `$pengguna` + jenis ticket + (khusus teknisi) apakah `$ticket->pic_id === $pengguna->id`, sesuai matrix Bagian 3.2. Gunakan Laravel `Gate`/Policy (`TicketPolicy::ubahStatus()`) supaya logic ini testable terpisah dan reusable di middleware route.
3. `DB::transaction()`:
   - `$statusLama = $ticket->status;`
   - `$ticket->update(['status' => $statusBaru]);`
   - `TicketHistori::create(['ticket_id' => $ticket->id, 'status_lama' => $statusLama, 'status_baru' => $statusBaru, 'catatan' => $catatan, 'oleh_pengguna_id' => $pengguna->id]);`
   - Jika `$statusBaru === StatusTicket::Selesai` dan `$ticket->jenis` termasuk `Pemasangan`: catat di `log_aktivitas` sebagai pengingat manual — **field khusus atau flag** (mis. kolom `perlu_aktivasi_manual` di ticket, di-set `true`) supaya muncul di dashboard NOC sebagai item yang perlu ditindaklanjuti manual ke Mikrotik (karena otomatisasi baru ada di Fase 6).
4. Setelah transaksi commit: dispatch notifikasi internal (Bagian 6).
5. Return `$ticket->refresh()`.

**Kenapa dibungkus action class, bukan langsung di controller**: supaya bisa dipanggil dari command Artisan (mis. auto-cancel ticket yang tidak ada progress lewat SLA), dari test, dan dari controller — tanpa duplikasi logic validasi.

---

## Bagian 6 — Notifikasi Internal

- Pakai Laravel Notification dengan **database channel** (bukan WA — WA blast baru masuk Fase 7 dan khusus untuk pelanggan, bukan notifikasi internal staf).
- Buat `App\Notifications\TicketDiassignNotification` — dikirim ke `$ticket->pic` saat `pic_id` di-set/berubah.
- Buat `App\Notifications\TicketStatusBerubahNotification` — dikirim ke `$ticket->dibuatOleh` dan `$ticket->pic` (kecuali penerima adalah `$pengguna` yang melakukan perubahan itu sendiri, supaya tidak notifikasi diri sendiri) setiap kali `UbahStatusTicketAction` berhasil.
- Tampilkan badge jumlah notifikasi belum dibaca di layout dashboard (Filament sudah punya widget notification bawaan bila Anda pakai Filament sebagai admin panel sesuai rekomendasi package).

---

## Bagian 7 — CRUD & Assignment

- Form tambah ticket: field kondisional per `jenis` —
  - `pemasangan`: butuh alamat baru (bisa reuse field alamat dari `pelanggan` bila pelanggan sudah terdaftar, atau input alamat manual bila prospek baru)
  - `gangguan`: butuh `layanan_pelanggan_id` (pilih dari layanan aktif milik pelanggan)
  - `pencabutan`/`pindah_alamat`: butuh `layanan_pelanggan_id` + catatan alasan
- Assignment PIC: dropdown PIC **difilter berdasarkan divisi yang sesuai jenis ticket** (mis. ticket gangguan hanya bisa di-assign ke pengguna role `noc` atau `teknisi`) — validasi ini di form request (`AssignPicRequest::rules()`), bukan cuma filter dropdown di frontend.
- Endpoint assign harus lewat action terpisah `App\Actions\Ticket\AssignPicAction` (pola sama dengan Bagian 5) — supaya histori & notifikasi assignment konsisten tercatat, bukan `$ticket->update(['pic_id' => ...])` polos.

---

## Bagian 8 — Permission (Spatie)

| Permission | Deskripsi |
|---|---|
| `ticket.buat` | Membuat ticket baru |
| `ticket.lihat_semua` | Lihat semua ticket lintas divisi (admin, super_admin) |
| `ticket.lihat_assigned` | Lihat ticket yang di-assign ke dirinya saja (teknisi) |
| `ticket.lihat_divisi` | Lihat semua ticket divisinya (noc) |
| `ticket.assign` | Assign/reassign PIC |
| `ticket.ubah_status` | Ubah status (detail siapa-boleh-apa tetap divalidasi matrix di action class, permission ini gerbang awal) |
| `ticket.batal` | Membatalkan ticket |
| `ticket.hapus` | Hapus ticket (soft delete, biasanya cuma `super_admin`) |

Assign ke role sesuai matrix Bagian 3.2 lewat seeder — jangan hardcode pengecekan role di controller (`if ($user->hasRole('teknisi'))`), pakai `$user->can('ticket.ubah_status')` supaya fleksibel diubah tanpa migrasi kode.

---

## Bagian 9 — Testing Checklist

- [ ] Unit test `StatusTicket::transisiValid()` — pastikan semua transisi sesuai matrix, termasuk yang **tidak** boleh (mis. `menunggu_konfirmasi → batal` harus `false`)
- [ ] Unit test `UbahStatusTicketAction` — transisi valid oleh role berwenang → sukses, tercatat di `ticket_histori`
- [ ] Unit test `UbahStatusTicketAction` — teknisi mencoba ubah status ticket yang **bukan** miliknya → `AuthorizationException`
- [ ] Unit test `UbahStatusTicketAction` — transisi tidak valid (mis. `baru → selesai` langsung) → `TransisiStatusTidakValidException`, tidak ada histori tercatat
- [ ] Feature test `AssignPicAction` — assign teknisi ke ticket jenis `pemasangan` sukses; assign `sales` ke ticket jenis `gangguan` → ditolak
- [ ] Feature test notifikasi — perubahan status memicu notifikasi ke pihak yang benar, tidak ke diri sendiri
- [ ] Feature test permission per role — `sales` tidak bisa akses endpoint ubah status sama sekali (gerbang permission), `teknisi` hanya melihat ticket assigned di listing-nya
- [ ] Manual test: buat ticket pemasangan → assign teknisi → teknisi ubah ke diproses → menunggu_konfirmasi → admin approve jadi selesai → cek flag `perlu_aktivasi_manual` muncul di dashboard NOC

---

## Bagian 10 — Yang Sengaja Ditunda

- Integrasi otomatis ke Mikrotik saat ticket selesai (Fase 6) — untuk sekarang cukup flag `perlu_aktivasi_manual` sebagai pengingat visual
- SLA breach alert otomatis (notifikasi saat `sla_target_selesai` terlewati) — bisa ditambah sebagai command terjadwal ringan setelah modul inti stabil, bukan prioritas di iterasi pertama
- Ticket dari pelanggan langsung via portal (self-service gangguan) — portal saat ini fokus billing (Fase 3), ticket dari pelanggan bisa jadi perluasan portal di fase lanjutan