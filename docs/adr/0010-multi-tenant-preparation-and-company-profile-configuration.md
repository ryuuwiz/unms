# Multi-Tenant Preparation and Company Profile Configuration

## Context
As the application is rebranded from UNMS to **GOBILLING**, the system needs to transition from hardcoded company and network metadata in invoices and portal templates to dynamic, configurable company information. Furthermore, while the platform currently operates in a single-company internal environment, the business roadmap requires future multi-tenancy capability (SaaS multi-tenant ISP management). 

Persisting settings as unstructured key-value rows or hardcoding config files prevents strong typing, breaks image asset pipelines, and makes tenant migration cumbersome. A structured, model-driven architecture is required to serve internal operations today while providing a clean runway to multi-tenancy tomorrow.

## Decisions

1. **Dedicated `perusahaan` Table**:
   - Company information is persisted in a dedicated `perusahaan` table rather than a generic key-value settings table.
   - Includes identity (`nama_perusahaan`, `nama_brand`, `tagline`, `logo_path`), contact info (`alamat`, `kota`, `kode_pos`, `telepon`, `whatsapp`, `email`, `website`), tax & banking (`npwp`, `nama_bank`, `nomor_rekening`, `atas_nama`), and invoice customization (`catatan_invoice`, `syarat_ketentuan`, `nama_penandatangan`, `jabatan_penandatangan`).
   - A boolean `is_default` flag designates the active operating company for the single-tenant deployment.

2. **Singleton Access & High-Performance Caching Pattern**:
   - Access to the active company profile is encapsulated in `Perusahaan::default()`.
   - The default model is cached in memory (`Cache::rememberForever('perusahaan_default', ...)`).
   - Cache invalidation is automatically triggered via model lifecycle hooks (`saved()` and `deleted()`).

3. **Logo Upload & DomPDF Base64 Image Pipeline**:
   - Company logo files are uploaded via Livewire 4 `WithFileUploads` and stored on the `public` disk (`company/`).
   - For DomPDF invoice generation, `Perusahaan` provides a `logo_base64` accessor (`data:image/png;base64,...`) to avoid filesystem URI path resolution errors in headless PDF printing.

4. **Dynamic Invoice Header & Portal Branding Integration**:
   - `InvoicePdfController` loads `Perusahaan::default()` and passes it to the `pdf.invoice` Blade view.
   - All hardcoded company strings in invoice PDFs, web invoices, and customer portal layouts are replaced with dynamic `$perusahaan` accessors and `config('app.name')`.

5. **Multi-Tenant Forward-Compatibility**:
   - When the platform transitions to full multi-tenancy, the `perusahaan` table directly acts as the `tenants` master table.
   - Domain models (`pelanggan`, `invoices`, `users`, `routers`) will receive a `perusahaan_id` foreign key with Global Scopes (`TenantScope`) without modifying the company profile schema.

6. **Authorization & Navigation**:
   - Modifying the company profile is restricted to users with the `super_admin` role (`role:super_admin`).
   - The settings interface is embedded into the existing Administrasi ➔ Settings navigation under `/settings/perusahaan`.
