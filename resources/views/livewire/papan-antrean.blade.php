<div wire:poll.30s class="flex min-h-screen flex-col gap-6 p-6 lg:p-10">
    @php
        $perusahaan = \App\Models\Perusahaan::default();
        $brandName = $perusahaan->nama_brand ?: config('app.name', 'GOBILLING');
    @endphp

    <header class="flex items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            @if ($perusahaan->logo_url)
                <img src="{{ $perusahaan->logo_url }}" alt="{{ $brandName }}" class="h-12 w-auto max-w-[220px] object-contain" />
            @else
                <x-app-logo-icon class="size-12 fill-current text-white" />
            @endif
            <div>
                <h1 class="text-3xl font-bold">Antrean Teknisi</h1>
                <p class="text-zinc-400">{{ $brandName }}</p>
            </div>
        </div>
        <div class="text-right text-zinc-400">
            <div class="text-3xl font-semibold text-white">{{ now()->format('H:i') }}</div>
            <div>{{ now()->translatedFormat('l, d F Y') }}</div>
        </div>
    </header>

    <div class="grid flex-1 gap-6 lg:grid-cols-3">
        @foreach ($kelompok as $judul => $daftarTiket)
            <section class="flex flex-col gap-3 rounded-xl border border-zinc-800 bg-zinc-900 p-4">
                <h2 class="flex items-center justify-between text-xl font-semibold">
                    {{ $judul }}
                    <span class="rounded-full bg-zinc-800 px-3 py-0.5 text-base">{{ $daftarTiket->count() }}</span>
                </h2>

                @forelse ($daftarTiket->take(\App\Livewire\PapanAntrean::BATAS_BARIS_PER_KELOMPOK) as $tiket)
                    <div wire:key="tiket-{{ $tiket->id }}" class="flex items-center gap-4 rounded-lg bg-zinc-800/60 p-3">
                        @php $fotoUrl = $tiket->pic?->fotoProfilUrl(); @endphp
                        <div class="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-zinc-700 text-xl font-bold text-zinc-300">
                            @if ($fotoUrl)
                                <img src="{{ $fotoUrl }}" alt="Foto {{ $tiket->pic->name }}" class="size-full object-cover" />
                            @elseif ($tiket->pic)
                                {{ $tiket->pic->initials() }}
                            @else
                                <flux:icon name="user" class="size-8 text-zinc-500" />
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="font-mono text-lg font-bold">{{ $tiket->nomor_ticket }}</div>
                            <div class="truncate text-zinc-300">{{ $tiket->pelanggan->namaDisamarkan() }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                <flux:badge size="sm" :color="$tiket->jenis->color()">{{ $tiket->jenis->label() }}</flux:badge>
                                <flux:badge size="sm" :color="$tiket->status->color()">{{ $tiket->status->label() }}</flux:badge>
                            </div>
                        </div>
                        <div class="max-w-[40%] text-right">
                            <div class="text-xs text-zinc-500">Teknisi</div>
                            <div class="truncate font-semibold">{{ $tiket->pic?->name ?? 'Menunggu Teknisi' }}</div>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-zinc-500">Tidak ada tiket.</p>
                @endforelse

                @if ($daftarTiket->count() > \App\Livewire\PapanAntrean::BATAS_BARIS_PER_KELOMPOK)
                    <p class="text-center text-zinc-400">dan {{ $daftarTiket->count() - \App\Livewire\PapanAntrean::BATAS_BARIS_PER_KELOMPOK }} lainnya</p>
                @endif
            </section>
        @endforeach
    </div>
</div>
