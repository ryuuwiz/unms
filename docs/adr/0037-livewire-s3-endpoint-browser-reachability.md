# Livewire S3 Uploads Require a Browser-Reachable AWS_ENDPOINT

## Context

Every `WithFileUploads` feature (company logo, customer KTP, ticket photos, payment slips) broke with a generic "gagal diunggah" ("failed to upload") validation error, in both local Sail dev and production. Root cause: when Livewire's temporary-upload disk resolves to the `s3` driver (which happens automatically whenever `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK` is unset and `FILESYSTEM_DISK=s3`), Livewire does not proxy uploads through the Laravel backend. Instead the **browser itself** PUTs file bytes directly to a presigned S3 URL, and fetches preview thumbnails via a presigned GET — both built from `AWS_ENDPOINT`, entirely bypassing the app (`vendor/livewire/livewire/src/Features/SupportFileUploads/{WithFileUploads,GenerateSignedUploadUrl,TemporaryUploadedFile}.php`).

`AWS_ENDPOINT` was set to `http://rustfs:9000` — the Docker Compose service name for RustFS, resolvable only *inside* the container network. Server-to-server S3 calls (MediaLibrary's final asset writes, `Storage::disk('s3')->put()`) worked fine from inside the container, masking the bug in backend-only testing. But the browser — on the host machine locally, or on the public internet in production — could never reach that hostname, so every direct-to-S3 PUT/GET failed client-side before Laravel's own validation ever ran.

## Decision

The temporary-upload disk and `AWS_ENDPOINT` must always be resolvable by whichever party actually uses them for network I/O — the browser included, not just the container:

- **Local Sail** (single instance, no replica-affinity concern): set `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=local`, forcing uploads/previews to proxy through the Laravel app instead of going direct-to-S3. `rustfs:9000` stays as `AWS_ENDPOINT` for the app's own (server-to-server) S3 calls, which is fine since the browser is never asked to reach it.
- **Production** (2+ replicas, ADR-0036, no confirmed sticky-session routing): keep temporary uploads on S3 (`LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3`, explicit rather than left to fall back implicitly) so any replica can serve a `save()` request regardless of which replica handled the upload — but point `AWS_ENDPOINT` at the same public domain already used for `AWS_URL` (`https://s3.buroq.gobilling.id`) instead of the internal `rustfs:9000`, so presigned URLs are reachable by both the container and any browser.

### Considered and rejected

- **`/etc/hosts` trick for local dev** (map `127.0.0.1 rustfs` on the host so the same hostname resolves both inside Docker and from the browser): rejected as the default — it mirrors production's shape more closely, but requires a manual one-time step per developer machine and per new hire, for no benefit in a single-instance local environment.
- **`local` disk for production temp uploads**: rejected — would reintroduce an intermittent "temp file not found" failure whenever the upload request and the later `save()` request land on different replicas.

## Consequences

- Local and production now intentionally use *different* temporary-upload disks (`local` vs `s3`) for the same feature — this is deliberate, not drift; do not "fix" them to match.
- Any future S3-compatible storage endpoint change (new provider, new domain) must keep `AWS_ENDPOINT` reachable by end-user browsers in whichever environment uses `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=s3`, not just by the app container.
- Guarded by `tests/Unit/EnvTemplateS3UploadTest.php` against `.env.docker.example` regressing back to an internal-only endpoint or an implicit (unset) temporary upload disk.
