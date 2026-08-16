# Customer Master Identity, Soft Deletes, and Sales Ownership Policy

## Context
PRD #2 establishes the Customer Management module as the single source of truth for all customer records that will be referenced by Subscriptions, Tickets, and Billing. The requirements demand robust phone validation for WhatsApp notifications, automated unique sequential customer coding (`CUST-000001`), sales-level record ownership protection, and preservation of customer history upon deactivation or termination.

## Decisions
1. **Customer Code Generation**: Automated sequential 6-digit zero-padded code (`CUST-000001`) generated atomically on model creation to prevent duplicates or collision under concurrent access. Customer codes are immutable once assigned.
2. **Phone Number Normalization**: Incoming phone numbers are validated against Indonesian cellular format regex (`^(08|\+628|628)[0-9]{8,13}$`) and normalized to standard E.164-compatible national representation (`628xxxxxxxxxx`) before persistence.
3. **Dual Status & Soft Deletes**: Customers use an operational `CustomerStatus` enum (`active`, `inactive`) for everyday lifecycle status toggles, complemented by Laravel's `SoftDeletes` (`deleted_at`) for administrative record deletion/archival to preserve historical integrity.
4. **Sales Scoped Authorization**: Sales staff have access to view all customers with a quick-filter tab (`Pelanggan Saya` vs `Semua Pelanggan`), but can only update/edit records they created (`created_by === auth()->id()`). Admin and Super Admin retain full CRUD access.
5. **Interactive Map Picker**: Leaflet.js and OpenStreetMap are integrated into Livewire forms via Alpine.js with two-way binding for manual text inputs and interactive map clicks/drag markers, without requiring paid 3rd-party API keys.
