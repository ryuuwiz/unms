@props([
    'sidebar' => false,
])

@php
    $perusahaan = \App\Models\Perusahaan::default();
    $brandName = $perusahaan->nama_brand ?: config('app.name', 'GOBILLING');
    $logoUrl = $perusahaan->logo_url;
@endphp

@if($sidebar)
    <flux:sidebar.brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg overflow-hidden {{ $logoUrl ? 'bg-white/10 dark:bg-zinc-800 p-0.5' : 'bg-accent-content text-accent-foreground' }}">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="size-full object-contain" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:sidebar.brand>
@else
    <flux:brand :name="$brandName" {{ $attributes }}>
        <x-slot name="logo" class="flex aspect-square size-8 items-center justify-center rounded-lg overflow-hidden {{ $logoUrl ? 'bg-white/10 dark:bg-zinc-800 p-0.5' : 'bg-accent-content text-accent-foreground' }}">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $brandName }}" class="size-full object-contain" />
            @else
                <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" />
            @endif
        </x-slot>
    </flux:brand>
@endif
