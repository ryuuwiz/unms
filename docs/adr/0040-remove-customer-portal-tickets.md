# Remove customer self-service tickets from the Portal Pelanggan

The Portal Pelanggan no longer offers tickets: the "Tiket" menu, `portal.tiket.*` routes, ticket create/list/detail pages, the in-app notification bell (its only feed was ticket status changes) and the staff "Tiket Baru dari Portal" notification were deleted. Tickets are now created by staff only; customers reach the ISP via WhatsApp (keyword `TIKET`, free-text to CS). This is a product decision by the owner to retire ticket self-service permanently.

- `SumberTicket::Portal` and existing `sumber = portal` rows are kept as read-only history; deleting the enum case would break casting on the staff ticket pages.
- `ticket_histori.is_internal` is kept (no schema change); every note is effectively internal now.
- The `{link_portal_tiket}` WhatsApp placeholder was removed and stripped from stored `wa_template` rows by migration, since `route('portal.tiket.show')` no longer exists and would otherwise throw in `WhatsappService::buildTicketParams()`.
