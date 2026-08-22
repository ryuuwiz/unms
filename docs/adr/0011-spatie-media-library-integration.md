# Spatie MediaLibrary Integration for File Uploads

## Context
Initial company logo handling relied on direct manual `Storage::disk('public')->putFile()` file operations and a discrete `logo_path` column on the `perusahaan` table. As the application grows to support more rich media (company logos, dark-mode variations, digital signature stamps, customer KTP attachments, ticket trouble photos, and payment slips), managing individual disk paths and orphan file cleanup across separate database tables becomes brittle and inconsistent.

Integrating **Spatie Laravel MediaLibrary** provides a unified polymorphic media management architecture, automated image conversions, non-destructive file lifecycle management, and consistent collection querying across all models.

## Decisions

1. **Polymorphic Media Relationship**:
   - Dropped the discrete `logo_path` column from the `perusahaan` table.
   - `Perusahaan` implements `Spatie\MediaLibrary\HasMedia` and uses `InteractsWithMedia`.
   - All assets are tracked via Spatie's polymorphic `media` table (`model_type`, `model_id`, `collection_name`, `file_name`, `mime_type`, `size`).

2. **Collection Registration & Conversions**:
   - Registered a `'logo'` single-file collection (`$this->addMediaCollection('logo')->singleFile()`) with supported mime types (`png`, `jpg`, `svg`, `webp`).
   - Defined non-queued conversions for thumbnails (`thumb`: 100x100 fit) and invoice headers (`invoice`: 400x120 contain).

3. **Livewire 4 File Ingestion**:
   - Livewire temporary files uploaded via `WithFileUploads` are ingested into Spatie MediaLibrary using `$perusahaan->addMedia($this->logo->getRealPath())->usingFileName($this->logo->getClientOriginalName())->toMediaCollection('logo')`.
   - Logo deletion calls `$perusahaan->clearMediaCollection('logo')`, automatically purging disk files and database records.

4. **DomPDF Data URI Compatibility**:
   - `Perusahaan::getLogoBase64Attribute()` queries the first media item in the `'logo'` collection and streams its binary contents into a Base64 data URI string (`data:{mime};base64,...`) for reliable PDF rendering without remote URL or filesystem symlink resolution issues.
