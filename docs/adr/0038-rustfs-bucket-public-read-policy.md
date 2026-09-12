# RustFS/S3 Bucket Needs an Explicit Public-Read Policy for Media URLs

## Context

After fixing `AWS_URL`/`AWS_ENDPOINT` browser-reachability (ADR-0037), media
URLs resolved to the right host but still 403'd in the browser with:

```xml
<Error><Code>AccessDenied</Code><Message>Access Denied</Message></Error>
```

RustFS (MinIO-compatible) denies anonymous `GetObject` by default. Flysystem
sets the `visibility` config (`public` in `config/filesystems.php`'s `s3`
disk) as a per-object ACL on `PutObject`, and `getObjectAcl` confirmed the
object *does* carry `FULL_CONTROL` for the owning canonical user — but MinIO/
RustFS-style servers gate anonymous access at the **bucket policy** level,
independent of per-object ACLs. A `public` object ACL alone does nothing for
anonymous GETs; the bucket itself needs an explicit policy statement allowing
`s3:GetObject` for `Principal: "*"`.

This is invisible from the Laravel/Media Library side entirely — every
call that matters (`toMediaCollection()`, `Storage::disk('s3')->put()`)
succeeds and reports success. The failure only surfaces as a browser-side 403
on the generated URL, with no application-side indicator anything is wrong.

## Decision

Provision the bucket policy as part of container boot, not as a manual
one-time step or documentation note a developer might miss:

- New Artisan command `app:ensure-public-media-bucket` calls
  `S3Client::putBucketPolicy()` with a public-read (`GetObject`-only) policy
  scoped to `AWS_BUCKET`, using the SDK client Flysystem already
  constructs (`Storage::disk('s3')->getClient()`) — no new dependency.
- Wired into `docker/entrypoint.sh` after `storage:link`, guarded with
  `|| true` (same pattern as the other boot-time cache/link commands):
  never blocks container boot if RustFS is briefly unreachable or the
  bucket doesn't exist yet.
- No-ops safely when `FILESYSTEM_DISK` isn't `s3` or `AWS_BUCKET` is unset,
  so it's a no-op for local-disk-only setups.
- Idempotent: `putBucketPolicy` fully replaces the policy document on every
  boot, safe to run on every replica on every deploy.

### Considered and rejected

- **Manual `mc policy set public` / RustFS console step per environment**:
  rejected — exactly the kind of infra step that's done once by whoever set
  up staging, forgotten for production, and invisible until a user reports
  broken images weeks later.
- **Presigned URLs everywhere (`getTemporaryUrl()`) instead of public
  objects**: rejected as the default — correct for the already-private `ktp`/
  `dokumen` collections (which stay on the `local` disk regardless, see
  `app/Models/Pelanggan.php`), but overkill for public assets like the
  company logo and ticket photos that have no access-control requirement.
  Would also require rewriting every `getFirstMediaUrl()` call site.

## Consequences

- Any new S3-compatible provider swapped in later (real AWS, R2, etc.) either
  already defaults to bucket-owner-only ACLs enforced through IAM (real AWS)
  — where this command's `putBucketPolicy` call is still valid and mostly a
  no-op-if-already-public — or needs the same bucket-policy treatment if it's
  another MinIO-family server.
- If the bucket ever needs to stop being public (e.g. all media moves behind
  presigned URLs), removing the `app:ensure-public-media-bucket` call from
  `entrypoint.sh` is not enough by itself — the previously-applied public
  policy stays in place until explicitly reverted with
  `deleteBucketPolicy()`.
