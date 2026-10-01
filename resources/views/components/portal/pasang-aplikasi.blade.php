@props(['brand'])

{{-- Tombol "Pasang Aplikasi" (ADR-0066): prompt instalasi browser bila tersedia, instruksi manual untuk iOS. --}}
<div
    x-data="{
        prompt: null,
        ios: /iphone|ipad|ipod/i.test(navigator.userAgent) && ! navigator.standalone,
        terpasang: window.matchMedia('(display-mode: standalone)').matches,
        panduan: false,
        init() {
            window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); this.prompt = e; });
            window.addEventListener('appinstalled', () => { this.prompt = null; this.terpasang = true; });
        },
        async pasang() {
            if (this.prompt) { this.prompt.prompt(); await this.prompt.userChoice; this.prompt = null; return; }
            this.panduan = true;
        },
    }"
    x-cloak
    x-show="! terpasang && (prompt || ios)"
    data-pasang-aplikasi
>
    <flux:button size="sm" variant="primary" icon="arrow-down-tray" x-on:click="pasang()">
        <span class="hidden sm:inline">Pasang Aplikasi</span>
        <span class="sm:hidden">Pasang</span>
    </flux:button>

    <div x-show="panduan" x-transition class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 p-4" x-on:click.self="panduan = false">
        <div class="w-full max-w-sm rounded-2xl bg-white dark:bg-zinc-900 p-5 space-y-3 text-sm">
            <div class="font-bold">Pasang {{ $brand->namaPendek() }} di iPhone</div>
            <ol class="list-decimal ps-5 space-y-1 text-zinc-600 dark:text-zinc-300">
                <li>Ketuk tombol <strong>Bagikan</strong> di Safari.</li>
                <li>Pilih <strong>Tambah ke Layar Utama</strong>.</li>
                <li>Ketuk <strong>Tambah</strong>.</li>
            </ol>
            <flux:button class="w-full" x-on:click="panduan = false">Mengerti</flux:button>
        </div>
    </div>
</div>
