# Package Bandwidth Modeling, Status Lifecycle, and Mikrotik Boundary Policy

## Context
PRD #3 defines the Package Management (Paket Internet) module for configuring internet service plans (bandwidth speed, price, and catalog availability). The design balances simplicity for commercial staff (Sales and Admins) with technical readiness for Mikrotik PPPoE queues and subscription lifecycle rules in upcoming modules.

## Decisions
1. **Asymmetric Bandwidth Schema**: Split bandwidth definition into `download_speed_mbps` and `upload_speed_mbps` (integers in Mbps) rather than a single ambiguous speed field. This directly supports ISP asymmetric packages (e.g. 20/10 Mbps) and Mikrotik rate-limit constraints without requiring future schema migrations.
2. **Whole Rupiah Pricing & No FUP**: Package prices are stored as `unsignedBigInteger` (`price`) representing whole Indonesian Rupiah (IDR). Kuota FUP is omitted as packages are strictly unlimited bandwidth plans.
3. **Decoupled Mikrotik Mapping**: The `packages` table contains only pure commercial/catalog attributes. All hardware-specific and router-specific mappings (`package ↔ router ↔ pppoe_profile_name`) are isolated to dedicated mapping models in the Mikrotik integration phase.
4. **Enum-Based Status Lifecycle & Soft Deletes**: Use `PackageStatus` backed enum (`active`, `inactive`) with `SoftDeletes` (`deleted_at`). Deactivating a package immediately hides it from new customer subscriptions while maintaining existing subscriber services. Deleting is reserved for unreferenced draft packages.
5. **Modal-Based Management UI**: Implement Create and Edit forms inside Flux UI modals on the `/packages` index view with full list view (default showing all packages) and quick status toggles for high-velocity catalog management.
6. **Auditing & Permissions**: Package changes (creation, updates, status toggles, soft deletes) are tracked via `AuditLogger`. Sales, NOC, and Technical staff have read-only access (`view_packages`), while Super Admin and Admin retain full management (`manage_packages`).
