<div class="space-y-8 pb-4">
    <div>
        <flux:heading size="xl" class="font-bold">Selamat datang kembali, {{ $nama }}</flux:heading>
        <flux:subheading>{{ now()->translatedFormat('l, d F Y') }}</flux:subheading>
    </div>

    @if (! $areaAdmin && ! $areaNoc && ! $areaTiket)
        <div class="p-8 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 text-center text-sm text-zinc-500 dark:text-zinc-400">
            Tidak ada ringkasan untuk peran Anda.
        </div>
    @endif

    @if ($areaAdmin)
        <x-dashboard-area judul="Ringkasan Pelanggan & Keuangan" subjudul="Monitoring cepat pelanggan aktif, pendapatan, dan tagihan bulan ini.">
            <livewire:dashboard.area-admin />
        </x-dashboard-area>
    @endif

    @if ($areaNoc)
        <x-dashboard-area judul="NOC & Infrastruktur" subjudul="Gambaran cepat router, status koneksi, paket layanan, dan konfigurasi jaringan.">
            <livewire:dashboard.area-noc />
        </x-dashboard-area>
    @endif

    @if ($areaTiket)
        <x-dashboard-area judul="Ticketing & Support" subjudul="Tiket yang menunggu tindakan kamu atau divisimu.">
            <livewire:dashboard.area-tiket />
        </x-dashboard-area>
    @endif
</div>
