@props(['judul', 'subjudul' => null])

{{-- Panel kartu Dashboard: judul, subjudul opsional, slot aksi di kanan atas, dan isi. --}}
<div {{ $attributes->class('p-5 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs space-y-4') }}>
    <div class="flex items-start justify-between gap-3">
        <div>
            <flux:heading size="lg">{{ $judul }}</flux:heading>
            @if ($subjudul)
                <flux:subheading>{{ $subjudul }}</flux:subheading>
            @endif
        </div>
        {{ $aksi ?? '' }}
    </div>

    {{ $slot }}
</div>
