@props(['segmen'])

{{-- Bar bertumpuk horizontal + legenda. $segmen: list<array{label: string, nilai: int, warna: string}>, warna = nama warna enum (color()). --}}
@php
    $total = array_sum(array_column($segmen, 'nilai'));
    // Kelas ditulis utuh agar terbaca Tailwind JIT.
    $kelas = fn (string $warna) => match ($warna) {
        'emerald', 'green' => 'bg-emerald-500',
        'amber' => 'bg-amber-500',
        'rose' => 'bg-rose-500',
        'sky' => 'bg-sky-500',
        'indigo' => 'bg-indigo-500',
        default => 'bg-zinc-400',
    };
@endphp

<div {{ $attributes->class('space-y-2') }}>
    <div class="flex h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
        @foreach ($segmen as $s)
            @if ($total > 0 && $s['nilai'] > 0)
                <div class="h-full {{ $kelas($s['warna']) }}" style="width: {{ $s['nilai'] / $total * 100 }}%" title="{{ $s['label'] }}: {{ $s['nilai'] }}"></div>
            @endif
        @endforeach
    </div>
    <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
        @foreach ($segmen as $s)
            <span class="inline-flex items-center gap-1.5">
                <span class="size-2 rounded-full {{ $kelas($s['warna']) }}"></span>{{ $s['label'] }}
                <span class="font-semibold text-zinc-700 dark:text-zinc-200">{{ number_format($s['nilai'], 0, ',', '.') }}</span>
            </span>
        @endforeach
    </div>
</div>
