@props([
    'src' => null,
    'target' => null,
    'aspect' => 'square',
    'fit' => 'contain',
    'deletable' => false,
    'deleteAction' => null,
    'deleteConfirm' => null,
    'deleteLabel' => 'Hapus',
    'icon' => 'photo',
    'emptyText' => 'Belum ada gambar',
    'alt' => 'Preview',
])

@php
    $boxClasses = $aspect === 'video' ? 'aspect-video w-full' : 'aspect-square size-28 shrink-0';
    $fitClass = $fit === 'cover' ? 'object-cover' : 'object-contain';
@endphp

<div class="flex flex-col items-center gap-2.5">
    <div
        class="relative flex {{ $boxClasses }} items-center justify-center overflow-hidden rounded-xl border border-zinc-200 bg-white shadow-xs dark:border-zinc-700 dark:bg-zinc-800">
        @if ($src)
            <img src="{{ $src }}" alt="{{ $alt }}" class="h-full w-full {{ $fitClass }}" />
        @else
            <div class="flex flex-col items-center justify-center p-2 text-center text-xs text-zinc-400 dark:text-zinc-500">
                <flux:icon :name="$icon" class="mb-1 size-8 opacity-50" />
                <span>{{ $emptyText }}</span>
            </div>
        @endif

        @if ($target)
            <div wire:loading wire:target="{{ $target }}"
                class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-black/60 text-[11px] font-medium text-white backdrop-blur-xs">
                <flux:icon name="arrow-path" class="size-5 animate-spin" />
                <span>Mengunggah...</span>
            </div>
        @endif
    </div>

    @if ($deletable && $src && $deleteAction)
        <flux:button variant="danger" size="sm" type="button" wire:click="{{ $deleteAction }}"
            wire:confirm="{{ $deleteConfirm }}" class="w-full justify-center">
            <div class="flex items-center gap-1">
                <flux:icon name="trash" class="mr-1 size-3.5" />
                {{ $deleteLabel }}
            </div>
        </flux:button>
    @endif
</div>
