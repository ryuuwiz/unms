@props(['user'])

{{-- Unggah foto profil via klik atau seret-lepas; langsung terunggah saat dipilih. Komponen Livewire induk wajib punya $fotoProfil, updatedFotoProfil() dan hapusFotoProfil(). --}}
<div class="flex items-center gap-4">
    <flux:avatar size="xl" :src="$user->fotoProfilUrl()" :initials="$user->initials()" />

    <div class="flex-1 space-y-2">
        <div
            x-data="{ dragging: false }"
            x-on:dragover="dragging = true"
            x-on:dragleave="dragging = false"
            x-on:drop="dragging = false"
            :class="dragging ? 'border-accent bg-accent/5' : 'border-zinc-300 dark:border-zinc-600'"
            class="relative flex flex-col items-center justify-center gap-1 rounded-lg border-2 border-dashed px-4 py-5 text-center transition hover:border-accent"
        >
            <input
                type="file"
                wire:model="fotoProfil"
                accept="image/*"
                aria-label="Unggah foto profil"
                class="absolute inset-0 cursor-pointer opacity-0"
            />

            <div wire:loading.remove wire:target="fotoProfil" class="pointer-events-none flex flex-col items-center gap-1">
                <flux:icon.photo class="size-6 text-zinc-400" />
                <flux:text size="sm" class="font-medium text-zinc-700 dark:text-zinc-200">
                    Klik untuk pilih foto atau seret ke sini
                </flux:text>
                <flux:text size="sm" class="text-zinc-400">JPG, PNG, WEBP · maks 2 MB</flux:text>
            </div>

            <div wire:loading wire:target="fotoProfil" class="pointer-events-none">
                <flux:text size="sm" class="flex items-center gap-2">
                    <flux:icon.arrow-path class="size-4 animate-spin" /> Mengunggah...
                </flux:text>
            </div>
        </div>

        <flux:error name="fotoProfil" />

        @if ($user->fotoProfilUrl())
            <flux:button size="sm" variant="ghost" icon="trash" wire:click="hapusFotoProfil" wire:confirm="Hapus foto profil?">
                Hapus foto
            </flux:button>
        @endif
    </div>
</div>
