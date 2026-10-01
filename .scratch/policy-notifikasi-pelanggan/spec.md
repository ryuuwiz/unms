# Spec: Kebijakan Notifikasi Billing, NOC, dan Ticketing

Status: ready-for-agent

## Problem Statement

Pelanggan menerima terlalu banyak notifikasi yang tidak semuanya dibutuhkan: notifikasi email invoice, notifikasi WhatsApp untuk perubahan status atau catatan ticket, dan notifikasi WhatsApp ketika status Ping Router berubah. Hal ini menambah volume pesan, mengaburkan informasi yang penting bagi pelanggan, serta meningkatkan risiko beban dan pembatasan pada gateway WhatsApp.

Di sisi operasional, staf NOC tetap membutuhkan lonceng internal untuk hasil pekerjaan MikroTik dan perubahan status router. Teknisi tetap membutuhkan disposisi ticket. Pelanggan hanya perlu diberi tahu ketika kunjungan teknisi benar-benar sudah dijadwalkan.

## Solution

Terapkan kebijakan kanal notifikasi yang lebih sempit:

- Billing tidak mengirim email invoice. Notifikasi billing yang sudah menggunakan WhatsApp tetap mengikuti alur WhatsApp yang ada.
- Ping Router hanya menghasilkan log dan lonceng internal untuk NOC/super admin ketika status berubah. Ping Router tidak mengirim WhatsApp.
- Ticketing tidak mengirim WhatsApp kepada pelanggan ketika ticket dibuat, status ticket berubah, atau catatan proses ditambahkan.
- Ticketing mengirim satu WhatsApp kepada pelanggan hanya ketika PIC teknisi dan jadwal kunjungan sudah ditetapkan.
- Notifikasi internal database untuk staf dan disposisi WhatsApp kepada teknisi tetap dipertahankan.

## User Stories

1. As a pelanggan, I want to receive no invoice email, so that billing communication stays in the chosen WhatsApp channel.
2. As a pelanggan, I want to receive invoice and payment messages through the existing WhatsApp billing flow, so that I still receive essential billing information.
3. As a pelanggan, I want to receive no WhatsApp message merely because a ticket was created, so that ticket creation does not create unnecessary noise.
4. As a pelanggan, I want to receive no WhatsApp message for every ticket status transition, so that I am not interrupted by internal workflow changes.
5. As a pelanggan, I want to receive no WhatsApp message for an internal or public ticket note, so that operational notes remain in the ticket workflow.
6. As a pelanggan, I want to receive a WhatsApp message when a technician and visit schedule are assigned to my ticket, so that I know when to expect the visit.
7. As a pelanggan, I want the scheduled visit message to include the ticket number, scheduled date and time, technician name, and service address, so that I can prepare for the visit.
8. As a pelanggan, I want no scheduling message when the ticket has no technician, so that I do not receive an incomplete appointment confirmation.
9. As a pelanggan, I want no scheduling message when the ticket has no visit time, so that an unconfirmed plan is not presented as an appointment.
10. As a teknisi, I want to receive the existing ticket disposition message, so that I have the information needed to handle the assigned work.
11. As a teknisi, I want the disposition message to remain separate from the customer appointment message, so that staff and customer communication remain appropriate to their audiences.
12. As a NOC operator, I want to see a database notification when a MikroTik customer job fails permanently, so that I can investigate operational failures.
13. As a NOC operator, I want router online/offline changes to appear in the internal notification bell, so that I can monitor connectivity without creating WhatsApp traffic.
14. As a NOC operator, I want a flapping router to produce no repeated WhatsApp messages, so that gateway volume stays controlled.
15. As a super admin, I want existing internal notification and WhatsApp behaviors unrelated to this policy to remain unchanged, so that the refactor does not remove operational alerts that are still required.
16. As an administrator, I want the new customer scheduling template to be manageable as a WhatsApp template, so that message content can be changed without modifying application logic.
17. As an administrator, I want the customer scheduling message to use the configured customer brand and company parameters, so that it is consistent with other outbound messages.
18. As a developer, I want the notification policy to be enforced at the observable dispatch boundary, so that future refactors cannot accidentally reintroduce disabled customer messages.

## Implementation Decisions

- Invoice creation and payment flows retain their existing WhatsApp behavior; only the invoice email channel is disabled. Payment gateway payer email fields remain payment-provider data and are not treated as application email notifications.
- The NOC notification service continues to support WhatsApp for explicitly critical permanent customer-job failures, but Ping Router invokes it without the WhatsApp option.
- Customer ticket status and ticket-note WhatsApp dispatches are removed from the ticket status action and ticket detail note flow.
- The ticket creation flow sends a customer scheduling WhatsApp only when both `pic_id` and `dijadwalkan_pada` are present and the customer has a usable phone number.
- The scheduling message uses a dedicated `tiket_penjadwalan_teknisi` template in the Ticket category. Its data includes customer identity, ticket number, technician name, scheduled time, address, brand, and ticket link where applicable.
- The ticket parameter builder exposes a localized scheduled technician date/time value for template rendering.
- Existing staff database notifications, technician disposition messages, invoice reminder messages, payment confirmation messages, and permanent MikroTik failure alerts remain in scope and are not removed.
- The template is provisioned idempotently for fresh environments and existing deployments without overwriting an administrator-edited template.
- No new customer notification preference, opt-out model, queue, gateway, or database table is introduced.

## Testing Decisions

- Tests must assert observable outbound behavior at the WhatsApp queue boundary and observable internal behavior at the notification database boundary, not private implementation details.
- Ticket creation with a customer phone number must prove that no `tiket_dibuat` message is queued without a complete technician appointment.
- Ticket status changes and ticket notes must prove that no customer WhatsApp message is queued.
- Ticket creation with both a technician and a visit schedule must prove that exactly the scheduling template is queued to the customer and that the message contains the technician and localized appointment time.
- Ticket assignment to a technician must continue to prove that the technician receives the existing disposition message.
- Ping Router status transitions must prove that internal NOC notifications are sent while no NOC WhatsApp queue entry is created.
- Invoice notification tests must prove the WhatsApp invoice flow remains available and that no email notification is dispatched.
- The scheduling template provisioning test must prove that a missing template is created and an existing customized template is not overwritten.
- Prior art is the existing feature coverage for ticket WhatsApp notifications, ticket status actions, invoice-terbit notifications, and MikroTik job notifications.
- The focused feature tests should run before the full suite; formatting and repository static analysis remain required release checks.

## Out of Scope

- Removing all billing WhatsApp messages, invoice reminders, payment confirmations, or payment gateway payer email fields.
- Removing internal database notifications for staff, NOC, or super admin users.
- Removing WhatsApp disposition messages sent to technicians.
- Adding customer replies, two-way ticket chat, delivery receipts, or a customer notification preferences screen.
- Changing ticket state transitions, technician assignment authorization, SLA calculation, or visit scheduling validation.
- Changing gateway rate limits, queue topology, retry policy, or WhatsApp provider integrations.
- Migrating or deleting historical WhatsApp queue records and notification records.
- Sending a scheduling notification when a schedule is edited after ticket creation unless that workflow is explicitly added later.

## Further Notes

- The existing domain documentation already treats email invoice notification as removed and treats Ping Router WhatsApp as intentionally disabled; this spec makes those decisions explicit alongside the ticketing policy.
- A later ticket may be needed if the product requires notifying customers when an existing ticket's technician or schedule is edited after creation. That is distinct from the current ticket-creation path.
- The highest-value integration seam is the creation/action boundary that enqueues WhatsApp messages, paired with the notification repository used by the internal bell.
