@props(['galeri'])

{{-- Galeri Foto Tiket -- lihat CONTEXT.md "Galeri Foto Tiket". Chip "Lihat foto" di tempat lain
     membuka lightbox ini lewat event window `buka-galeri` dengan id media. --}}
@php($semuaFoto = $galeri->semuaFoto())

<div
    {{ $attributes->class('bg-white dark:bg-zinc-800 p-4 sm:p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-5') }}
    wire:key="galeri-{{ md5(implode(',', array_column($semuaFoto, 'id'))) }}"
    x-data="{
        foto: @js($semuaFoto),
        aktif: null,
        sentuhX: null,
        buka(id) { const i = this.foto.findIndex(f => f.id === id); this.aktif = i >= 0 ? i : null },
        geser(arah) { if (this.aktif === null) return; this.aktif = (this.aktif + arah + this.foto.length) % this.foto.length },
        selesaiSentuh(x) { if (this.sentuhX === null) return; const d = x - this.sentuhX; if (Math.abs(d) > 40) this.geser(d < 0 ? 1 : -1); this.sentuhX = null },
    }"
    x-on:buka-galeri.window="buka($event.detail.id)"
    x-on:keydown.escape.window="aktif = null"
    x-on:keydown.arrow-right.window="geser(1)"
    x-on:keydown.arrow-left.window="geser(-1)"
>
    <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2 border-b border-zinc-100 dark:border-zinc-700/60 pb-3">
        <flux:icon name="photo" class="size-5 text-violet-600 dark:text-violet-400" />
        Galeri Foto Tiket
        <span class="text-xs font-medium text-zinc-500">({{ count($semuaFoto) }} foto)</span>
    </h3>

    @if ($galeri->kosong())
        <p class="text-sm italic text-zinc-400">Belum ada foto pada tiket ini.</p>
    @endif

    @foreach ($galeri->kelompok as $kelompok)
        <section class="space-y-2">
            <div class="flex items-center gap-2 text-sm">
                <span class="font-semibold text-zinc-800 dark:text-zinc-200">{{ $kelompok['label'] }}</span>
                @if ($kelompok['wajib'])
                    <flux:badge size="sm" :color="$kelompok['foto'] ? 'green' : 'amber'">Wajib</flux:badge>
                @endif
                <span class="text-xs text-zinc-500">{{ count($kelompok['foto']) }} foto</span>
            </div>

            @if ($kelompok['foto'] === [])
                <div class="rounded-lg border border-dashed border-amber-300 bg-amber-50/60 p-4 text-center text-xs text-amber-700 dark:border-amber-700/60 dark:bg-amber-950/20 dark:text-amber-300">
                    Belum ada foto
                </div>
            @else
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    @foreach ($kelompok['foto'] as $foto)
                        <button type="button" x-on:click="buka({{ $foto['id'] }})" class="group block overflow-hidden rounded-lg border border-zinc-200 bg-zinc-100 text-left dark:border-zinc-700 dark:bg-zinc-900">
                            <img src="{{ $foto['url'] }}" alt="{{ $kelompok['label'] }} #{{ $loop->iteration }}" loading="lazy" class="aspect-[4/3] w-full object-cover transition group-hover:opacity-90" />
                            <span class="block px-2.5 py-1.5 text-[11px] text-zinc-500">Diunggah {{ $foto['waktu'] }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </section>
    @endforeach

    {{-- Lightbox --}}
    <template x-teleport="body">
        <div
            x-show="aktif !== null"
            x-cloak
            x-transition.opacity
            class="fixed inset-0 z-[9999] flex flex-col bg-black/95"
            x-on:touchstart="sentuhX = $event.changedTouches[0].clientX"
            x-on:touchend="selesaiSentuh($event.changedTouches[0].clientX)"
            role="dialog"
            aria-modal="true"
        >
            <div class="flex items-center justify-between gap-3 px-4 py-3 text-sm text-white">
                <div class="min-w-0">
                    <div class="truncate font-semibold" x-text="aktif !== null ? foto[aktif].kategori : ''"></div>
                    <div class="text-xs text-white/70" x-text="aktif !== null ? `${aktif + 1} / ${foto.length} · Diunggah ${foto[aktif].waktu}` : ''"></div>
                </div>
                <div class="flex shrink-0 items-center gap-1">
                    <a x-bind:href="aktif !== null ? foto[aktif].url : '#'" target="_blank" class="flex size-11 items-center justify-center rounded-full hover:bg-white/10" title="Buka ukuran asli">
                        <flux:icon name="arrow-top-right-on-square" class="size-5" />
                    </a>
                    <button type="button" x-on:click="aktif = null" class="flex size-11 items-center justify-center rounded-full hover:bg-white/10" title="Tutup (Esc)">
                        <flux:icon name="x-mark" class="size-6" />
                    </button>
                </div>
            </div>

            <div class="relative flex flex-1 items-center justify-center overflow-hidden px-2 pb-4" x-on:click.self="aktif = null">
                <img x-bind:src="aktif !== null ? foto[aktif].url : ''" alt="" class="max-h-full max-w-full object-contain" />

                <template x-if="foto.length > 1">
                    <div>
                        <button type="button" x-on:click="geser(-1)" class="absolute left-2 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70" title="Sebelumnya">
                            <flux:icon name="chevron-left" class="size-6" />
                        </button>
                        <button type="button" x-on:click="geser(1)" class="absolute right-2 top-1/2 flex size-11 -translate-y-1/2 items-center justify-center rounded-full bg-black/50 text-white hover:bg-black/70" title="Berikutnya">
                            <flux:icon name="chevron-right" class="size-6" />
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </template>
</div>
