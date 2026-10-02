@props([
    'user' => null,
    'peran',
    'kosong' => 'Belum Ditugaskan',
])

{{-- Foto Profil staf di tiket: persegi 4×4 cm, inisial bila tanpa foto -- lihat CONTEXT.md "Foto Profil".
     Memakai berkas asli, bukan thumb 128px, agar tidak buram di ukuran ini. --}}
<div {{ $attributes->class('flex flex-col items-start gap-2') }}>
    <span class="text-xs text-zinc-500">{{ $peran }}</span>

    @if ($user)
        @php $fotoUrl = $user->getFirstMediaUrl('foto_profil'); @endphp
        <div class="size-[4cm] shrink-0 overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900">
            @if ($fotoUrl)
                <img src="{{ $fotoUrl }}" alt="Foto {{ $user->name }}" class="size-full object-cover" />
            @else
                <div class="flex size-full items-center justify-center text-4xl font-bold text-zinc-500 dark:text-zinc-400">{{ $user->initials() }}</div>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-1.5">
            <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</span>
            @unless ($user->isActive())
                <flux:badge size="sm" color="zinc">Nonaktif</flux:badge>
            @endunless
        </div>
    @else
        <div class="flex size-[4cm] shrink-0 items-center justify-center rounded-lg border border-dashed border-zinc-300 text-zinc-400 dark:border-zinc-600">
            <flux:icon name="user" class="size-10" />
        </div>
        <span class="text-sm italic text-zinc-500">{{ $kosong }}</span>
    @endif
</div>
