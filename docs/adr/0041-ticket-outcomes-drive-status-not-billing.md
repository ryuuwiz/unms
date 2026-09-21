# Ticket outcomes drive status automatically; billing decisions stay manual

Ticket results now move Pelanggan and layanan status automatically (Pemasangan created/Selesai/Batal → Pelanggan `ReqPemasangan`/`PemasanganSelesai`/`BelumTerpasang`; Pencabutan Selesai → layanan `Berhenti`; layanan status changes → derived Pelanggan status via `LayananPelangganObserver`). Creating the Data Registrasi Billing (paket, router, IP Pool, PPP credentials, first invoice) and the Pindah Alamat fee invoice stay manual admin actions, surfaced as banners on the finished ticket, because a ticket carries none of that data and the fee/first-invoice choice is commercial. Fully automatic activation (define the layanan up front, activate and invoice on Selesai, as Sonar does) was rejected for now as a larger change; see `docs/research/isp-billing-workflow-automation.md`.

- Pelanggan status is derived from all layanan (Aktif > Suspend → Expired > all Berhenti → Off) so one suspended layanan never marks a multi-layanan customer Expired (ADR-0020).
- Unpaid invoices stay open on Berhenti; there is no automatic Suspend → Berhenti timeout.
- The layanan create form no longer restricts customer search to `Aktif`, since a new customer is not Aktif until the layanan is.
