# Persist storage/app via Dokploy volume; Livewire temp uploads on local disk

Production Livewire temp uploads on `s3` failed (browser PUTs directly to the bucket via presigned URL, which needs bucket CORS that `app:ensure-public-media-bucket` does not set). We now set `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=local` so uploads proxy through the app, while `FILESYSTEM_DISK`/`MEDIA_DISK` stay `s3` for final files. Because `local` writes to `storage/app/private/livewire-tmp`, `storage/app` is mounted as a Dokploy named volume so deploys do not erase it. This supersedes the production half of ADR-0037.

## Considered Options

- **Keep `s3` and add bucket CORS**: fixes the root cause and stays replica-safe, but was rejected for now; revisit if scaling out.
- **Dockerfile `VOLUME` instruction**: rejected, creates a new anonymous volume per deploy; the named mount lives in Dokploy.

## Consequences

- Single replica only (ADR-0036's 2+ replica premise no longer holds for uploads); named volumes are per-node and local temp files are not visible across replicas.
- Dokploy env `LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK=local` must be set manually; the template is not applied automatically.
