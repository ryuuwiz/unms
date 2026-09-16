---
paths:
  - resources/views/livewire/**/*.blade.php
  - resources/views/components/searchable-select.blade.php
  - app/Livewire/Concerns/HasSearchableOptions.php
---

# `<flux:select searchable>` is a no-op in Flux Free

Flux Free v2.19.0 (no `flux-pro` installed) declares a `searchable` prop on `<flux:select>`
(`vendor/livewire/flux/stubs/resources/views/flux/select/index.blade.php`) but never
consumes it — the only shipped variant, `select/variants/default.blade.php`, is a plain
native `<select>` with zero reference to `$searchable`, and there is no JS implementation
anywhere in the free package. Adding `searchable` to a `<flux:select>` changes nothing;
it will compile, render, and silently do nothing.

Don't re-add it. For a dropdown that genuinely needs search (customer/package-sized
lists that can grow), use the `App\Livewire\Concerns\HasSearchableOptions` trait plus
`<x-searchable-select field="..." label="..." />` (`resources/views/components/searchable-select.blade.php`)
instead — see `App\Livewire\Ticket\Create::searchableFields()` for a reference
implementation.

For small, admin-provisioned lists (routers, IP pools — a handful of rows in practice,
not customer-scale) a plain `<flux:select>` without `searchable` is fine; don't build
search UI for those.
