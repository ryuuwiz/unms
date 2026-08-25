# Encrypted Customer Identity and Document Storage Architecture

## Context
Compliance with Indonesia's Personal Data Protection Law (UU PDP) requires robust protection for sensitive Personally Identifiable Information (PII) such as National Identity Cards (KTP) and contractual agreements (MOU / Service Level Agreements / Subscription Forms). 

The initial database schema contained a legacy unencrypted nullable `gambar_ktp_path` column on the `pelanggan` table. Furthermore, the default Spatie MediaLibrary disk (`public`) is accessible via unauthenticated web URLs, creating significant data exposure risks if identity documents are stored there.

We require a unified polymorphic architecture for managing customer KTP images and multi-document legal attachments (MOU, contracts, power of attorney) with defense-in-depth protection: at-rest encryption, private storage isolation, role-based granular access control, dynamic watermarking on preview, and comprehensive audit trails.

## Decisions

1. **Polymorphic Media Collections on Private Storage**:
   - `Pelanggan` implements `Spatie\MediaLibrary\HasMedia` and uses `InteractsWithMedia`.
   - The legacy `gambar_ktp_path` column is dropped from the `pelanggan` table via migration.
   - Two distinct media collections are registered on `Pelanggan`:
     - `'ktp'`: Single-file collection (`$this->addMediaCollection('ktp')->singleFile()->useDisk('local')`), accepting `image/jpeg`, `image/png`, `image/webp`.
     - `'dokumen'`: Multi-file collection (`$this->addMediaCollection('dokumen')->useDisk('local')`), accepting `application/pdf`, `image/jpeg`, `image/png` with custom properties for document categorization (`jenis_dokumen`: `mou`, `formulir`, `surat_kuasa`, `lainnya`) and optional descriptions.

2. **At-Rest Binary Encryption & Storage Isolation**:
   - Files are stored on the non-public private disk (`storage/app/private/media/`).
   - File binary payloads are encrypted at-rest using Laravel's application-level encryption (`Crypt::encryptString()` / AES-256-CBC) prior to being committed to the storage disk.
   - Direct web server access is prevented as the private directory is outside `public/`.

3. **Secure Gated Streaming & Dynamic Watermarking**:
   - Access to documents is routed exclusively through authenticated, policy-guarded controllers (`/admin/pelanggan/{pelanggan}/ktp/preview`, `/admin/pelanggan/{pelanggan}/dokumen/{media}/stream`).
   - For KTP images, the backend dynamically decrypts the binary and composites a semi-transparent diagnostic watermark (*"HANYA UNTUK DOKUMEN GOBILLING • [STAFF_NAME] • [TIMESTAMP]"*) using PHP GD/Imagick before outputting the stream.
   - For PDF documents, the stream is delivered inline with anti-hotlink tokens and security headers (`Content-Disposition: inline`, `X-Content-Type-Options: nosniff`).

4. **Granular RBAC and Audit Logging**:
   - Distinct permissions:
     - `pelanggan.lihat_ktp`: Restricted to `super_admin`, `admin`, and `sales` (only for customers they personally created). NOC and Teknisi cannot view KTP images.
     - `pelanggan.lihat_dokumen` & `pelanggan.unggah_dokumen`: Accessible to `super_admin`, `admin`, `sales`, and `cs`.
   - Every view, stream, download, and deletion of a KTP or MOU document is recorded into `Spatie\Activitylog` with caller identity, IP address, and timestamp.

5. **Livewire Staff Management & UI Privacy**:
   - Creation & Edit: KTP upload widget with preview and validation in `Pelanggan\Create` and `Pelanggan\Edit`.
   - Detail View (`Pelanggan\Show`): KTP is blurred/masked by default in the new "Dokumen & Legalitas" tab. Staff must click "Buka KTP" to reveal the watermarked image in a secure modal, triggering an audit log event. Multi-document MOU management is accessible within the same tab via interactive upload/delete modals.
