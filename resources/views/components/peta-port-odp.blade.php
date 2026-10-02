@props([
    'peta',
    'interaktif' => false,
])

{{-- Peta Port ODP -- lihat CONTEXT.md "Peta Port ODP". Ketukan pada kotak menampilkan keterangan port;
     pada mode interaktif juga memilih port lewat aksi Livewire pilihPort(). --}}
@php
    $warna = [
        'kosong' => 'bg-emerald-50 border-emerald-300 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-700 dark:text-emerald-300',
        'terpakai' => 'bg-rose-50 border-rose-300 text-rose-800 dark:bg-rose-950/40 dark:border-rose-700 dark:text-rose-300',
        'rusak' => 'bg-zinc-100 border-zinc-300 text-zinc-500 dark:bg-zinc-800 dark:border-zinc-600 dark:text-zinc-400',
        'dipesan' => 'bg-amber-50 border-amber-300 text-amber-800 dark:bg-amber-950/40 dark:border-amber-700 dark:text-amber-300',
    ];
@endphp

<div {{ $attributes->class('space-y-3') }} x-data="{ info: null }">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-200">
            {{ $peta->odp->nama_odp }}
            <span class="ml-1 text-xs font-medium text-zinc-500">{{ $peta->jumlahTerpakai() }}/{{ $peta->jumlahPort() }} terpakai</span>
        </div>
        <div class="flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-zinc-500">
            <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-emerald-400"></span>Kosong</span>
            <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-rose-400"></span>Terpakai</span>
            <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-zinc-400"></span>Rusak</span>
            <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-amber-400"></span>Dipesan</span>
            <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm ring-2 ring-blue-500"></span>{{ $interaktif ? 'Dipilih' : 'Port layanan ini' }}</span>
        </div>
    </div>

    @if ($peta->jumlahPort() === 0)
        <p class="text-xs italic text-zinc-500">ODP ini belum punya data port.</p>
    @else
        <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
            @foreach ($peta->ports as $port)
                <button
                    type="button"
                    @if ($interaktif && $port['dapat_dipilih'])
                        wire:click="pilihPort({{ $port['id'] }})"
                    @endif
                    x-on:click="info = info === {{ $port['id'] }} ? null : {{ $port['id'] }}"
                    @class([
                        'relative flex min-h-11 flex-col items-center justify-center rounded-lg border-2 px-1 py-1.5 text-center transition',
                        $warna[$port['status']],
                        'ring-2 ring-blue-500 ring-offset-1 dark:ring-offset-zinc-800' => $port['terpilih'],
                        'cursor-pointer hover:brightness-95' => $interaktif && $port['dapat_dipilih'],
                        'cursor-help' => ! ($interaktif && $port['dapat_dipilih']),
                    ])
                    aria-pressed="{{ $port['terpilih'] ? 'true' : 'false' }}"
                >
                    <span class="text-base font-bold leading-none">{{ $port['nomor'] }}</span>
                    <span class="sr-only">Port {{ $port['nomor'] }}</span>
                    <span class="mt-0.5 text-[10px] leading-none">{{ $port['label'] }}</span>
                </button>
            @endforeach
        </div>

        @foreach ($peta->ports as $port)
            <div x-show="info === {{ $port['id'] }}" x-cloak class="rounded-lg bg-zinc-50 p-2.5 text-xs text-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-300">
                <strong>Port {{ $port['nomor'] }}</strong> — {{ $port['label'] }}
                @if ($port['keterangan'])
                    <div class="mt-0.5">{{ $port['keterangan'] }}</div>
                @endif
                @if ($interaktif && ! $port['dapat_dipilih'] && $port['alasan'])
                    <div class="mt-0.5 text-zinc-500">{{ $port['alasan'] }}</div>
                @endif
            </div>
        @endforeach
    @endif
</div>
