@props(['title' => null])

<x-layouts::app.sidebar :title="$title ?? null">
    @if (isset($headerNav))
        <x-slot:headerNav>
            {{ $headerNav }}
        </x-slot:headerNav>
    @endif

    <flux:main>
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
