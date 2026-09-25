<div wire:poll.10s.visible>
    <flux:dropdown position="bottom" align="start">
        <flux:button variant="ghost" size="sm" icon="bell" class="relative" aria-label="Notifikasi">
            @if ($belumDibaca > 0)
                <span class="absolute -top-1 -right-1 min-w-4 rounded-full bg-red-600 px-1 text-[10px] leading-4 text-white">{{ $belumDibaca > 99 ? '99+' : $belumDibaca }}</span>
            @endif
        </flux:button>

        <flux:menu class="w-80 max-w-[calc(100vw-2rem)]">
            <div class="flex items-center justify-between px-2 py-1">
                <flux:heading size="sm">Notifikasi</flux:heading>
                @if ($belumDibaca > 0)
                    <flux:button variant="ghost" size="xs" wire:click="tandaiSemuaDibaca">Tandai semua dibaca</flux:button>
                @endif
            </div>
            <flux:menu.separator />

            @forelse ($notifikasi as $n)
                <a href="{{ $n->data['url'] ?? '#' }}" wire:click="tandaiDibaca('{{ $n->id }}')" wire:key="notif-{{ $n->id }}"
                   @class(['block rounded-md px-2 py-2 text-sm hover:bg-zinc-100 dark:hover:bg-zinc-700', 'bg-zinc-50 dark:bg-zinc-800' => $n->read_at === null])>
                    <div @class(['font-medium', 'text-red-600 dark:text-red-400' => ($n->data['status'] ?? null) === 'failed'])>{{ $n->data['title'] ?? 'Notifikasi' }}</div>
                    <div class="line-clamp-3 text-xs text-zinc-500">{{ $n->data['message'] ?? '' }}</div>
                    <div class="mt-0.5 text-[11px] text-zinc-400">{{ $n->created_at->diffForHumans() }}</div>
                </a>
            @empty
                <div class="px-2 py-4 text-center text-sm text-zinc-500">Belum ada notifikasi.</div>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
