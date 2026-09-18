<div class="space-y-6 max-w-2xl">
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Siklus Tagihan</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Hari jatuh tempo dan hari terbit invoice setiap bulan.
            Semua paket berdurasi 1 bulan.</p>
    </div>

    <flux:card class="p-6">
        <form wire:submit="save" class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <flux:input wire:model.live="hari_jatuh_tempo" type="number" min="1" max="28"
                    label="Hari Jatuh Tempo" description="Tanggal jatuh tempo dan expired layanan (1-28)." />
                <flux:input wire:model.live="hari_terbit_invoice" type="number" min="1" max="28"
                    label="Hari Terbit Invoice" description="Tanggal invoice terbit otomatis (1-28)." />
            </div>

            <p class="text-sm text-zinc-600 dark:text-zinc-300">
                Invoice terbit tanggal <strong>{{ $hari_terbit_invoice }}</strong>, jatuh tempo tanggal
                <strong>{{ $hari_jatuh_tempo }}</strong>@if ($leadDays !== null) (H-{{ $leadDays }})@endif. Perubahan hanya berlaku untuk invoice yang
                terbit sesudahnya dan perpanjangan layanan berikutnya.
            </p>

            <div class="flex justify-end">
                <flux:button type="submit" variant="primary">Simpan</flux:button>
            </div>
        </form>
    </flux:card>
</div>
