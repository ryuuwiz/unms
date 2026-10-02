---
paths:
  - app/Livewire/**/*.php
  - config/livewire.php
  - .env.docker.example
---

# Livewire File Uploads & S3

## `AWS_ENDPOINT` must be reachable by the browser, not just the app container
Whenever `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK` resolves to a disk with the `s3` driver (it falls back to `FILESYSTEM_DISK` when unset — `Livewire\Features\SupportFileUploads\FileUploadConfiguration::disk()`), Livewire has the **browser itself** PUT/GET directly against `AWS_ENDPOINT` via presigned URLs for every `WithFileUploads` component (upload *and* preview thumbnail) — it does not proxy through the Laravel backend at all. An endpoint that only resolves inside the Docker network (e.g. the Compose service name `rustfs:9000`) breaks every file upload in the app client-side with a generic "gagal diunggah" / "failed to upload" validation error, even though server-to-server S3 calls (MediaLibrary writes, `Storage::disk('s3')->put()`) keep working fine — don't let that mask the bug during backend-only debugging. See `docs/adr/0037-livewire-s3-endpoint-browser-reachability.md`.

Current split (deliberate, do not unify with s3; ADR-0067 supersedes the production half of ADR-0037):
- **Local Sail and production**: `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=local` — uploads proxy through the app. Production s3 direct upload failed (bucket needs CORS for browser PUT). `FILESYSTEM_DISK`/`MEDIA_DISK` stay `s3` for final files; `AWS_ENDPOINT` must still be a public domain in production (Storage::url / any presigned URL).
- Requires **single replica** and `storage/app` on a persistent Dokploy volume (`livewire-tmp` lives in `storage/app/private`). Scaling to 2+ replicas needs s3 temp disk + bucket CORS again.

Guarded by `tests/Unit/EnvTemplateS3UploadTest.php` against `.env.docker.example` regressing.
