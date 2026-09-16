@props([
    'field',
    'label' => null,
    'placeholder' => 'Cari...',
    'required' => false,
])

@php
    $hasValue = ! empty($this->{$field});
    $isOpen = ($this->searchOpen[$field] ?? false) || ! $hasValue;
@endphp

<flux:field>
    @if ($label)
        <flux:label>{{ $label }}@if ($required) <span class="text-red-500">*</span>@endif</flux:label>
    @endif

    @if (! $isOpen)
        <button type="button" wire:click="openSearchable('{{ $field }}')"
            class="flex h-10 w-full items-center justify-between rounded-lg border border-zinc-200 border-b-zinc-300/80 bg-white px-3 text-start text-sm text-zinc-700 shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300">
            <span class="truncate">{{ $this->searchableSelectedLabel($field) }}</span>
            <flux:icon name="chevron-down" class="size-4 shrink-0 text-zinc-400" />
        </button>
    @else
        <div class="space-y-1">
            <div class="flex gap-2">
                <flux:input type="search" wire:model.live.debounce.300ms="searchTerms.{{ $field }}"
                    placeholder="{{ $placeholder }}" autofocus class="flex-1" />
                @if ($hasValue)
                    <flux:button type="button" variant="ghost" size="sm" icon="x-mark"
                        wire:click="closeSearchable('{{ $field }}')" />
                @endif
            </div>

            @if (trim($this->searchTerms[$field] ?? '') !== '')
                <div
                    class="max-h-56 divide-y divide-zinc-100 overflow-y-auto rounded-lg border border-zinc-200 bg-white shadow-sm dark:divide-zinc-700/50 dark:border-zinc-700 dark:bg-zinc-800">
                    @forelse ($this->searchResults($field) as $result)
                        <button type="button" wire:click="selectSearchable('{{ $field }}', {{ $result['id'] }})"
                            class="block w-full px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700/50">
                            {{ $result['label'] }}
                        </button>
                    @empty
                        <p class="px-3 py-2 text-xs text-zinc-400">Tidak ditemukan.</p>
                    @endforelse
                </div>
            @endif
        </div>
    @endif

    <flux:error name="{{ $field }}" />
</flux:field>
