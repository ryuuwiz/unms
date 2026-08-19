# Ticket Module Architecture and State Machine

## Context
Operating an ISP network management system requires managing complex lifecycle tasks across internal teams: new installation requests (`Pemasangan`), connection troubleshooting (`Gangguan`), service relocation (`PindahAlamat`), and disconnection (`Pencabutan`). Direct database status mutations in controllers easily cause race conditions, missing transition validations, skipped audit logs, and inconsistent assignment notifications across roles (`super_admin`, `admin`, `sales`, `noc`, `teknisi`). A robust, action-driven, and policy-guarded architecture is required to ensure complete state integrity and transparent operational history.

## Decisions

1. **Dedicated Enums in `App\Enums\Ticket\*`**:
   - The ticket lifecycle is formalized into 5 PHP 8.4 backed string enums:
     - `JenisTicket`: `pemasangan`, `pencabutan`, `gangguan`, `pindah_alamat`
     - `PrioritasTicket`: `rendah` (+7d), `sedang` (+3d), `tinggi` (+24h), `darurat` (+4h)
     - `DivisiTicket`: `admin`, `customer_service`, `sales`, `noc`, `teknisi`
     - `StatusTicket`: `baru`, `diproses`, `menunggu_konfirmasi`, `selesai`, `batal`
     - `SumberTicket`: `manual`, `sistem`
   - Transition validation is encapsulated inside `StatusTicket::transisiValid(): array`.

2. **Action-Driven Single Source of Truth (`UbahStatusTicketAction` & `AssignPicAction`)**:
   - Status mutations and PIC assignments cannot be updated via raw model updates or generic controller methods.
   - `UbahStatusTicketAction::execute()` validates the state transition graph, verifies role & actor ownership through `TicketPolicy`, executes inside a database transaction (`DB::transaction()`), automatically writes an immutable `TicketHistori` record, flags `perlu_aktivasi_manual` for completed installations, and dispatches asynchronous notifications.
   - `AssignPicAction::execute()` validates eligible department roles (e.g. technicians/NOC for trouble tickets), logs assignment history, and dispatches assignment notifications.

3. **Immutable History Log (`TicketHistori`)**:
   - `TicketHistori` is strictly read-only and append-only. It records `status_lama`, `status_baru`, `catatan`, and `oleh_pengguna_id`. No update or delete operations are exposed.

4. **Model Lifecycle & Automatic SLA Calculation**:
   - In `Ticket::booted()` during `creating()`:
     - Unique standardized ticket number `TCK-YYYY-NNNNNN` is generated atomically.
     - Target completion timestamp `sla_target_selesai` is computed from `prioritas->durasiSlaHours()`.

5. **Customer Relationship Integrity**:
   - `pelanggan_id` is foreign-keyed and required (`NOT NULL`). Prospects for installation tickets are first captured in the master `pelanggan` table with `status = prospek`.

6. **Granular Policy Matrix with Standard Spatie Permissions**:
   - Standard Spatie permissions (`ticket.lihat`, `ticket.buat`, `ticket.ubah`, `ticket.hapus`, `ticket.assign`) act as entry gates.
   - `TicketPolicy` enforces contextual business rules:
     - `teknisi` can only view and mutate status on tickets where `pic_id === user->id`.
     - `sales` can only cancel tickets created by themselves (`dibuat_oleh === user->id`).
     - `noc` can manage all tickets under the technical domain (troubles, installations).

7. **Internal Notifications**:
   - Uses Laravel Database Notifications (`TicketDiassignNotification`, `TicketStatusBerubahNotification`) displayed in the dashboard without external messaging dependencies.
