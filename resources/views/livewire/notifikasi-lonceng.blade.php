{{-- Di bawah modal (z-50); toast dipindah ke kanan atas agar tidak bertumpuk. --}}
{{-- keep-alive: tetap poll saat tab di latar belakang, justru saat notifikasi browser paling berguna. --}}
<div wire:poll.10s.keep-alive="periksaBaru" class="fixed right-4 bottom-4 z-40 lg:right-6 lg:bottom-6"
    x-data="{
        izin: 'Notification' in window ? Notification.permission : 'unsupported',
        async minta() { this.izin = await Notification.requestPermission() },
        tampilkan(daftar) {
            if (this.izin !== 'granted') return
            daftar.forEach(n => {
                // tag = id: beberapa tab terbuka tidak menggandakan notifikasi yang sama.
                const notif = new Notification(n.title, { body: n.body, tag: n.id })
                notif.onclick = () => { window.focus(); $wire.tandaiDibaca(n.id); if (n.url) window.location.href = n.url; notif.close() }
            })
        },
    }"
    x-on:notifikasi-browser.window="tampilkan($event.detail.notifikasi)">
    <flux:dropdown position="top" align="end">
        <button type="button" aria-label="Notifikasi{{ $belumDibaca > 0 ? " ({$belumDibaca} belum dibaca)" : '' }}" title="Notifikasi"
            class="group relative flex size-14 cursor-pointer items-center justify-center rounded-full bg-linear-to-br from-sky-500 to-indigo-600 text-white shadow-xl shadow-indigo-600/40 ring-4 ring-white transition duration-200 hover:scale-110 hover:shadow-2xl hover:shadow-indigo-600/50 focus:outline-none focus-visible:ring-sky-300 active:scale-95 dark:ring-zinc-900">
            @if ($belumDibaca > 0)
                <span class="absolute inset-0 animate-ping rounded-full bg-indigo-500 opacity-30 motion-reduce:hidden"></span>
                <flux:icon name="bell-alert" variant="solid" class="relative size-7 origin-top group-hover:animate-bounce motion-reduce:animate-none" />
                <span class="absolute -top-1.5 -right-1.5 flex min-w-6 items-center justify-center rounded-full bg-red-600 px-1.5 text-xs leading-6 font-bold text-white shadow-md ring-2 ring-white dark:ring-zinc-900">{{ $belumDibaca > 99 ? '99+' : $belumDibaca }}</span>
            @else
                <flux:icon name="bell" variant="solid" class="relative size-7" />
            @endif
        </button>

        <flux:menu class="w-80 max-w-[calc(100vw-2rem)]">
            <div class="flex items-center justify-between px-2 py-1">
                <flux:heading size="sm">Notifikasi</flux:heading>
                @if ($belumDibaca > 0)
                    <flux:button variant="ghost" size="xs" wire:click="tandaiSemuaDibaca">Tandai semua dibaca</flux:button>
                @endif
            </div>
            <template x-if="izin === 'default'">
                <button type="button" x-on:click="minta()" class="mx-2 mb-1 block w-[calc(100%-1rem)] rounded-md bg-zinc-100 px-2 py-1.5 text-left text-xs text-zinc-700 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700">
                    Aktifkan notifikasi browser agar tetap mendapat pemberitahuan saat tab tidak dibuka.
                </button>
            </template>
            <template x-if="izin === 'denied'">
                <p class="px-2 pb-1 text-xs text-zinc-500">Notifikasi browser diblokir; izinkan lewat pengaturan situs di browser.</p>
            </template>
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
