<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Tiket Saya</h1>
            <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Daftar tiket keluhan dan permohonan layanan yang telah Anda ajukan</p>
        </div>
        <flux:button :href="route('portal.tiket.create')" wire:navigate icon="plus" variant="primary" size="sm">
            Ajukan Tiket Baru
        </flux:button>
    </div>

    @if($tickets->isEmpty())
        <flux:card class="p-12 text-center text-zinc-500 space-y-3">
            <flux:icon icon="ticket" class="size-10 mx-auto text-zinc-400" />
            <div class="text-sm font-medium">Belum ada tiket yang diajukan.</div>
            <flux:button :href="route('portal.tiket.create')" wire:navigate variant="primary" size="sm">
                Ajukan Tiket Pertama
            </flux:button>
        </flux:card>
    @else
        <div class="space-y-3">
            @foreach($tickets as $ticket)
                <flux:card class="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:border-zinc-300 dark:hover:border-zinc-700 transition-colors">
                    <div class="space-y-1.5">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono font-bold text-sm text-zinc-900 dark:text-zinc-100">{{ $ticket->nomor_ticket }}</span>
                            <flux:badge size="sm" variant="pill" :color="$ticket->status->color()">
                                {{ $ticket->status->label() }}
                            </flux:badge>
                            <flux:badge size="sm" variant="pill" :color="$ticket->jenis->color()">
                                {{ $ticket->jenis->label() }}
                            </flux:badge>
                        </div>
                        <div class="text-xs text-zinc-500 dark:text-zinc-400">
                            @if($ticket->layananPelanggan)
                                Site: {{ $ticket->layananPelanggan->site_id }}
                                @if($ticket->layananPelanggan->paketLayanan)
                                    &bull; {{ $ticket->layananPelanggan->paketLayanan->nama_paket }}
                                @endif
                                &bull;
                            @endif
                            Diajukan {{ $ticket->created_at->diffForHumans() }}
                        </div>
                        @if(! $ticket->status->isTerminal() && $ticket->sla_target_selesai)
                            <div class="text-xs {{ $ticket->isOverdue() ? 'text-rose-500 font-semibold' : 'text-zinc-400' }}">
                                @if($ticket->isOverdue())
                                    <flux:icon icon="exclamation-triangle" class="size-3 inline" />
                                    Melewati target: {{ $ticket->sisaWaktuSla() }}
                                @else
                                    <flux:icon icon="clock" class="size-3 inline" />
                                    Target selesai: {{ $ticket->sla_target_selesai->format('d M Y, H:i') }}
                                @endif
                            </div>
                        @endif
                    </div>
                    <flux:button :href="route('portal.tiket.show', $ticket)" wire:navigate variant="ghost" size="sm" icon-trailing="chevron-right">
                        Lihat Detail
                    </flux:button>
                </flux:card>
            @endforeach
        </div>

        <div>{{ $tickets->links() }}</div>
    @endif
</div>
