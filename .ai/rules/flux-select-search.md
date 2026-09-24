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

# `<flux:select placeholder>` needs an explicit empty option; never `:value="null"`

The Flux Free select stub renders `placeholder` as `<option value="" disabled selected>`. When the
bound property is `null`/`''` (or a value missing from the list), the browser can't keep a disabled
option selected and visually shows the **first real option** instead — the user thinks it is
chosen, Livewire still holds `null`, and `required` validation fails on submit.

Always add an enabled empty option right after the opening tag, repeating the placeholder text:

```blade
<flux:select wire:model="kategoriId" placeholder="Pilih kategori...">
    <flux:select.option value="">Pilih kategori...</flux:select.option>
    @foreach ($kategoris as $kategori) ... @endforeach
</flux:select>
```

Apply it to every select whose property can start empty or hold a value not in the options (e.g.
a list filtered to online routers / active packages). Selects bound to a property that always has a
valid default (enum defaults like `status = 'aktif'`) don't need it.

Never write `<flux:select.option :value="null">`: the option stub omits the `value` attribute when
it is null, so the browser submits the option's **text** (e.g. "Bukan di perumahan") as the value.
Use `value=""` for a "none" choice; Livewire hydrates `''` into a `?int` property as `null`.
