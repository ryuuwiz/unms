@php
    use App\Livewire\Portal\StatusKoneksi;

    [$label, $warna, $ikon] = match ($keadaan) {
        StatusKoneksi::KEADAAN_ONLINE => ['Online', 'text-emerald-600 dark:text-emerald-400', 'signal'],
        StatusKoneksi::KEADAAN_OFFLINE => ['Offline', 'text-rose-600 dark:text-rose-400', 'signal-slash'],
        StatusKoneksi::KEADAAN_TIDAK_TERSEDIA => ['Status tidak tersedia', 'text-zinc-500 dark:text-zinc-400', 'question-mark-circle'],
        default => ['Belum tersambung', 'text-zinc-500 dark:text-zinc-400', 'clock'],
    };
@endphp

<div class="mt-3 space-y-2" data-status-koneksi="{{ $keadaan }}">
    <div class="flex items-center justify-between gap-2 text-xs">
        <div class="flex items-center gap-1.5 font-semibold {{ $warna }}">
            <flux:icon :icon="$ikon" class="size-4" />
            <span>{{ $label }}</span>
            @if ($lamaSesi)
                <span class="font-normal text-zinc-500 dark:text-zinc-400">· tersambung {{ $lamaSesi }}</span>
            @endif
        </div>

        @if ($keadaan !== StatusKoneksi::KEADAAN_BELUM_TERSAMBUNG)
            <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="muatUlang" wire:loading.attr="disabled">
                {{ __('Cek ulang') }}
            </flux:button>
        @endif
    </div>

    @if ($keadaan === StatusKoneksi::KEADAAN_TIDAK_TERSEDIA)
        <p class="text-[11px] text-zinc-500 dark:text-zinc-400">Status sambungan sedang tidak dapat dibaca. Coba lagi beberapa saat lagi.</p>
    @endif

    @if ($dibatasi)
        <p class="text-[11px] text-amber-600 dark:text-amber-400">Terlalu sering memeriksa. Tunggu sebentar lalu coba lagi.</p>
    @endif

    @if ($perluBayar)
        <div class="rounded-lg bg-amber-50 dark:bg-amber-950/40 p-2.5 text-[11px] text-amber-700 dark:text-amber-300 flex items-center justify-between gap-2">
            <span>Layanan diisolir. Lunasi tagihan untuk mengaktifkan kembali.</span>
            <flux:button size="xs" variant="primary" :href="route('portal.invoice.index')" wire:navigate>
                {{ __('Bayar') }}
            </flux:button>
        </div>
    @endif
</div>
