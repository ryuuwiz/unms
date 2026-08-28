<div class="space-y-6">
    <div>
        <flux:button :href="route('portal.tiket.index')" wire:navigate variant="ghost" icon="arrow-left" size="sm" class="mb-3">
            Kembali ke Tiket Saya
        </flux:button>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="space-y-1">
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-xl font-bold font-mono text-zinc-900 dark:text-zinc-100">{{ $ticket->nomor_ticket }}</h1>
                    <flux:badge :color="$ticket->status->color()">{{ $ticket->status->label() }}</flux:badge>
                    <flux:badge :color="$ticket->jenis->color()" variant="outline">{{ $ticket->jenis->label() }}</flux:badge>
                </div>
                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                    Diajukan {{ $ticket->created_at->format('d M Y, H:i') }}
                </p>
            </div>

            {{-- Tombol Batalkan (hanya status Baru) --}}
            @if($ticket->status === \App\Enums\Ticket\StatusTicket::Baru)
                <flux:button wire:click="openBatalkanModal" variant="danger" size="sm" icon="x-circle">
                    Batalkan Tiket
                </flux:button>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Kolom Kiri: Info Tiket --}}
        <div class="lg:col-span-2 space-y-5">
            {{-- SLA --}}
            @if($ticket->sla_target_selesai && ! $ticket->status->isTerminal())
                <flux:card class="p-4 flex items-center gap-3 {{ $ticket->isOverdue() ? 'border-rose-300 dark:border-rose-800 bg-rose-50 dark:bg-rose-950/30' : 'border-sky-200 dark:border-sky-800 bg-sky-50 dark:bg-sky-950/30' }}">
                    <flux:icon icon="{{ $ticket->isOverdue() ? 'exclamation-triangle' : 'clock' }}" class="size-5 shrink-0 {{ $ticket->isOverdue() ? 'text-rose-500' : 'text-sky-500' }}" />
                    <div>
                        <p class="text-xs font-semibold {{ $ticket->isOverdue() ? 'text-rose-700 dark:text-rose-300' : 'text-sky-700 dark:text-sky-300' }}">
                            {{ $ticket->isOverdue() ? 'Melewati Target Penyelesaian' : 'Target Penyelesaian' }}
                        </p>
                        <p class="text-sm font-bold {{ $ticket->isOverdue() ? 'text-rose-900 dark:text-rose-200' : 'text-sky-900 dark:text-sky-200' }}">
                            {{ $ticket->sla_target_selesai->format('d M Y, H:i') }}
                            <span class="font-normal text-xs ml-1">({{ $ticket->sisaWaktuSla() }})</span>
                        </p>
                    </div>
                </flux:card>
            @endif

            {{-- Deskripsi --}}
            <flux:card class="p-5 space-y-3">
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Deskripsi Keluhan</p>
                <p class="text-sm text-zinc-800 dark:text-zinc-200 whitespace-pre-wrap">{{ $ticket->deskripsi }}</p>

                @if($ticket->getFirstMedia('foto_kendala'))
                    <div class="pt-2">
                        <span class="text-xs font-medium text-zinc-500 block mb-1">Lampiran Foto:</span>
                        <a href="{{ $ticket->getFirstMediaUrl('foto_kendala') }}" target="_blank" class="inline-block">
                            <img src="{{ $ticket->getFirstMediaUrl('foto_kendala') }}" alt="Foto Kendala" class="h-28 w-auto object-cover rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm" />
                        </a>
                    </div>
                @endif
            </flux:card>

            {{-- Histori Tiket --}}
            <flux:card class="p-5 space-y-4">
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Riwayat Penanganan</p>

                @if($ticket->histori->isEmpty())
                    <p class="text-sm text-zinc-400 dark:text-zinc-500 text-center py-4">Belum ada riwayat penanganan.</p>
                @else
                    <div class="space-y-4">
                        @foreach($ticket->histori as $h)
                            <div class="flex gap-3">
                                <div class="shrink-0 mt-1">
                                    <div class="size-2 rounded-full bg-zinc-400 dark:bg-zinc-600 mt-1.5"></div>
                                </div>
                                <div class="space-y-1 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        @if($h->status_lama && $h->status_lama !== $h->status_baru)
                                            <flux:badge size="sm" :color="$h->status_lama->color()" variant="outline">{{ $h->status_lama->label() }}</flux:badge>
                                            <flux:icon icon="arrow-right" class="size-3 text-zinc-400" />
                                        @endif
                                        <flux:badge size="sm" :color="$h->status_baru->color()">{{ $h->status_baru->label() }}</flux:badge>
                                        <span class="text-xs text-zinc-400">{{ $h->created_at->format('d M Y, H:i') }}</span>
                                    </div>
                                    @if($h->catatan)
                                        <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $h->catatan }}</p>
                                    @endif
                                    @if($h->getFirstMedia('foto_pengerjaan'))
                                        <div class="pt-1">
                                            <a href="{{ $h->getFirstMediaUrl('foto_pengerjaan') }}" target="_blank" class="inline-block">
                                                <img src="{{ $h->getFirstMediaUrl('foto_pengerjaan') }}" alt="Bukti Pengerjaan" class="h-24 w-auto object-cover rounded-lg border border-zinc-200 dark:border-zinc-700 shadow-sm" />
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </div>

        {{-- Kolom Kanan: Ringkasan Info --}}
        <div class="space-y-4">
            <flux:card class="p-4 space-y-3">
                <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wide">Info Layanan</p>
                @if($ticket->layananPelanggan)
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between">
                            <span class="text-zinc-500">Site ID</span>
                            <span class="font-mono font-semibold text-zinc-900 dark:text-zinc-100">{{ $ticket->layananPelanggan->site_id }}</span>
                        </div>
                        @if($ticket->layananPelanggan->paketLayanan)
                            <div class="flex justify-between">
                                <span class="text-zinc-500">Paket</span>
                                <span class="font-medium text-zinc-800 dark:text-zinc-200">{{ $ticket->layananPelanggan->paketLayanan->nama_paket }}</span>
                            </div>
                        @endif
                    </div>
                @else
                    <p class="text-sm text-zinc-400">—</p>
                @endif
            </flux:card>
        </div>
    </div>

    {{-- Modal Batalkan Tiket --}}
    <flux:modal wire:model="showBatalkanModal" class="md:w-96">
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Batalkan Tiket</flux:heading>
                <flux:subheading>Jelaskan alasan pembatalan tiket <strong>{{ $ticket->nomor_ticket }}</strong>.</flux:subheading>
            </div>
            <flux:field>
                <flux:label>Alasan Pembatalan</flux:label>
                <flux:textarea
                    wire:model="alasanBatalkan"
                    id="alasan_batalkan"
                    rows="3"
                    placeholder="Contoh: Masalah sudah teratasi sendiri."
                />
                <flux:error name="alasanBatalkan" />
            </flux:field>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">Batal</flux:button>
                </flux:modal.close>
                <flux:button wire:click="batalkanTiket" variant="danger" icon="x-circle">
                    Ya, Batalkan Tiket
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
