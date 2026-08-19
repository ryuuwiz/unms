# User and Customer Portal Impersonation Architecture

## Context
As the UNMS network management system evolves, super administrators frequently need to troubleshoot access permission issues for internal staff roles (`admin`, `sales`, `noc`, `teknisi`) and simulate customer experience in the Customer Portal (`AkunPelanggan`). Manually changing passwords or asking for user credentials poses severe security risks. To address this, `lab404/laravel-impersonate` is integrated into UNMS with strict role-based access constraints, multi-guard session management, UI indicators, critical route protection, and security audit logging.

## Decisions

1. **Strict Origin Authorization (`canImpersonate`)**:
   - Only active users (`status === UserStatus::Active`) holding the `super_admin` role are authorized to initiate an impersonation session (`canImpersonate(): bool`).
   - Customer accounts (`AkunPelanggan`) are explicitly forbidden from initiating impersonation (`canImpersonate() => false`).

2. **Guarded Target Scope (`canBeImpersonated`)**:
   - **Internal Staff (`User`)**: Only active users (`UserStatus::Active`) who do **not** possess the `super_admin` role can be impersonated. This prevents horizontal privilege escalation or takeover between super administrators.
   - **Customer Portal (`AkunPelanggan`)**: Only active customers associated with active master customer records (`CustomerStatus::Active`) can be impersonated.

3. **Multi-Guard Aware Controller & Dynamic Redirects**:
   - UNMS utilizes two distinct session authentication guards: `web` (internal staff) and `pelanggan` (customer portal).
   - Dedicated `App\Http\Controllers\ImpersonateController` manages transitions between guards:
     - **Staff Take (`guard: web`)**: Switches session quietly and redirects to `route('dashboard')`.
     - **Customer Take (`guard: pelanggan`)**: Switches session from `web` to `pelanggan` quietly and redirects to `route('portal.dashboard')`.
     - **Leave**: Restores original `super_admin` identity under `web` guard and redirects to `route('users.index')` or `route('pelanggan.index')`.

4. **Security Hardening (`impersonate.protect`)**:
   - Routes handling credential mutation—such as password change (`settings/password`, `portal/ganti-password`), passkey registration, and profile deletion—are protected by the `impersonate.protect` middleware to prevent impersonators from inadvertently altering real user credentials.

5. **Visual Awareness (Top Sticky Banner & Action Triggers)**:
   - A high-visibility top sticky banner (`<x-impersonation-banner />`) is rendered across both staff (`layouts.app.sidebar`) and customer portal (`layouts.portal`) layouts whenever `is_impersonating()` is active, showing the target identity and a one-click *"Kembali ke Akun Asli"* button.
   - Impersonation triggers are integrated into the `users.index` table row actions, `users.edit` header, and `pelanggan.show` header.

6. **Audit Trail & Event Logging**:
   - Sytem listens to `TakeImpersonation` and `LeaveImpersonation` events via `LogImpersonationActivity` to record structured audit logs (impersonator ID, impersonated ID, guard, timestamp, IP) for compliance and forensic investigation.
