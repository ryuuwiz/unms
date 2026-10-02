{{-- Dipasang dua kali (sidebar desktop & header mobile): hanya instance di breakpoint aktif yang mem-poll dan
     memunculkan notifikasi browser. Poll lewat setInterval, bukan wire:poll, agar instance yang tersembunyi diam;
     tetap berjalan saat tab di latar belakang, justru saat notifikasi browser paling berguna. --}}
<div
    x-data="{
        izin: 'Notification' in window ? Notification.permission : 'unsupported',
        timer: null,
        aktif() { return window.matchMedia('(min-width: 1024px)').matches === {{ $posisi === 'sidebar' ? 'true' : 'false' }} },
        init() { this.timer = setInterval(() => { if (this.aktif()) $wire.periksaBaru() }, 10000) },
        destroy() { clearInterval(this.timer) },
        async minta() { this.izin = await Notification.requestPermission() },
        tampilkan(daftar) {
            if (this.izin !== 'granted' || ! this.aktif()) return
            daftar.forEach(n => {
                // tag = id: beberapa tab terbuka tidak menggandakan notifikasi yang sama.
                const notif = new Notification(n.title, { body: n.body, tag: n.id })
                notif.onclick = () => { window.focus(); $wire.tandaiDibaca(n.id); if (n.url) window.location.href = n.url; notif.close() }
            })
        },
    }"
    x-on:notifikasi-browser.window="tampilkan($event.detail.notifikasi)">
    @php
        $label = 'Notifikasi'.($belumDibaca > 0 ? " ({$belumDibaca} belum dibaca)" : '');
    @endphp
    <flux:dropdown :position="$posisi === 'sidebar' ? 'top' : 'bottom'" :align="$posisi === 'sidebar' ? 'start' : 'end'">
        @if ($posisi === 'sidebar')
            <flux:sidebar.item :tooltip="$label" aria-label="{{ $label }}" class="cursor-pointer">
                <x-slot:icon>
                    @include('livewire.partials.ikon-lonceng')
                </x-slot:icon>
                Notifikasi
            </flux:sidebar.item>
        @else
            <flux:button variant="ghost" square aria-label="{{ $label }}" title="{{ $label }}">
                @include('livewire.partials.ikon-lonceng')
            </flux:button>
        @endif

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
