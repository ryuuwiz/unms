<div class="relative">
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="ghost" class="relative p-2 rounded-lg text-zinc-600 dark:text-zinc-300 hover:text-zinc-900 dark:hover:text-white" aria-label="Notifikasi">
            <flux:icon icon="bell" class="size-5" />
            @if($unreadCount > 0)
                <span class="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white shadow">
                    {{ $unreadCount > 9 ? '9+' : $unreadCount }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80 max-w-[90vw] p-0">
            <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-100 dark:border-zinc-800">
                <div class="font-bold text-sm text-zinc-900 dark:text-zinc-100 flex items-center gap-1.5">
                    <span>Notifikasi</span>
                    @if($unreadCount > 0)
                        <flux:badge size="xs" color="indigo">{{ $unreadCount }} baru</flux:badge>
                    @endif
                </div>
                @if($unreadCount > 0)
                    <button
                        wire:click="tandaiSemuaDibaca"
                        type="button"
                        class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium cursor-pointer"
                    >
                        Tandai semua dibaca
                    </button>
                @endif
            </div>

            <div class="max-h-80 overflow-y-auto divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse($notifications as $notif)
                    @php
                        $isUnread = is_null($notif->read_at);
                        $data = $notif->data;
                        $url = $data['url'] ?? route('portal.tiket.index');
                    @endphp
                    <a
                        href="{{ $url }}"
                        wire:navigate
                        class="block px-4 py-3 hover:bg-zinc-50 dark:hover:bg-zinc-800/60 transition-colors {{ $isUnread ? 'bg-indigo-50/40 dark:bg-indigo-950/20' : '' }}"
                    >
                        <div class="flex items-start gap-2.5">
                            @if($isUnread)
                                <div class="size-2 rounded-full bg-indigo-600 mt-1.5 shrink-0"></div>
                            @else
                                <div class="size-2 rounded-full bg-transparent mt-1.5 shrink-0"></div>
                            @endif
                            <div class="space-y-0.5 flex-1 min-w-0">
                                <p class="text-xs font-semibold text-zinc-900 dark:text-zinc-100 truncate">
                                    {{ $data['title'] ?? 'Pemberitahuan' }}
                                </p>
                                <p class="text-xs text-zinc-600 dark:text-zinc-400 line-clamp-2">
                                    {{ $data['message'] ?? '-' }}
                                </p>
                                <span class="text-[10px] text-zinc-400 block pt-0.5">
                                    {{ $notif->created_at->diffForHumans() }}
                                </span>
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="py-8 text-center text-zinc-400 text-xs">
                        <flux:icon icon="bell-slash" class="size-6 mx-auto mb-1 text-zinc-300 dark:text-zinc-600" />
                        Belum ada notifikasi
                    </div>
                @endforelse
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
