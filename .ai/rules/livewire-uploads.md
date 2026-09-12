---
paths:
  - app/Livewire/**/*.php
  - config/livewire.php
  - .env.docker.example
---

# Livewire File Uploads & S3

## `AWS_ENDPOINT` must be reachable by the browser, not just the app container
Whenever `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK` resolves to a disk with the `s3` driver (it falls back to `FILESYSTEM_DISK` when unset — `Livewire\Features\SupportFileUploads\FileUploadConfiguration::disk()`), Livewire has the **browser itself** PUT/GET directly against `AWS_ENDPOINT` via presigned URLs for every `WithFileUploads` component (upload *and* preview thumbnail) — it does not proxy through the Laravel backend at all. An endpoint that only resolves inside the Docker network (e.g. the Compose service name `rustfs:9000`) breaks every file upload in the app client-side with a generic "gagal diunggah" / "failed to upload" validation error, even though server-to-server S3 calls (MediaLibrary writes, `Storage::disk('s3')->put()`) keep working fine — don't let that mask the bug during backend-only debugging. See `docs/adr/0037-livewire-s3-endpoint-browser-reachability.md`.

Current split (deliberate, do not unify):
- **Local Sail**: `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=local` — single instance, so proxying through the app is simplest and needs no per-developer machine setup. `AWS_ENDPOINT=http://rustfs:9000` stays fine here since the browser never touches it.
- **Production** (`.env.docker.example`): `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3` explicitly (needed for replica-safety — 2+ replicas per ADR-0036, no confirmed sticky-session routing), with `AWS_ENDPOINT` pointed at the same public domain as `AWS_URL` (`https://s3.buroq.gobilling.id`), never an internal-only service name.

Guarded by `tests/Unit/EnvTemplateS3UploadTest.php` against `.env.docker.example` regressing.
