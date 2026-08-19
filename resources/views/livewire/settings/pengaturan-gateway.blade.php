<div class="max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Pengaturan Payment Gateway Xendit</flux:heading>
        <flux:subheading>Konfigurasi biaya transaksi (admin fee) dan kebijakan subsidi biaya gateway bagi pelanggan</flux:subheading>
    </div>

    <flux:card class="p-6">
        <form wire:submit="save" class="space-y-6">
            <div class="space-y-4">
                <flux:heading size="sm">Status & Mode Operasional</flux:heading>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:checkbox wire:model="is_active" label="Aktifkan Pembayaran Gateway" />
                        <flux:description>Izinkan pelanggan melakukan pembayaran otomatis via Xendit.</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:checkbox wire:model="sandbox_mode" label="Mode Sandbox / Development" />
                        <flux:description>Gunakan lingkungan simulasi/testing (tanpa uang riil).</flux:description>
                    </flux:field>
                </div>
            </div>

            <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 space-y-4">
                <flux:heading size="sm">Beban Biaya Transaksi (Admin Fee)</flux:heading>

                <flux:field>
                    <flux:checkbox wire:model="bebankan_ke_pelanggan" label="Bebankan Biaya Transaksi ke Pelanggan" />
                    <flux:description>Jika dicentang, total tagihan yang dibayar pelanggan akan ditambah biaya admin di bawah. Jika tidak dicentang, biaya transaksi ditanggung/disubsidi oleh ISP.</flux:description>
                </flux:field>
            </div>

            <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 space-y-4">
                <flux:heading size="sm">Nominal Biaya Admin</flux:heading>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <flux:input
                        wire:model="fee_va_nominal"
                        type="number"
                        step="100"
                        min="0"
                        label="Biaya Admin Virtual Account (Rp)"
                        placeholder="Contoh: 4000"
                        description="Nominal flat per pembuatan VA"
                    />

                    <flux:input
                        wire:model="fee_qris_persen"
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        label="Biaya Admin QRIS (%)"
                        placeholder="Contoh: 0.70"
                        description="Persentase potongan QRIS (MDR)"
                    />
                </div>

                <flux:input
                    wire:model="fee_qris_nominal"
                    type="number"
                    step="100"
                    min="0"
                    label="Biaya Tambahan Flat QRIS (Rp) (Opsional)"
                    placeholder="Contoh: 0"
                    description="Biaya flat tambahan untuk transaksi QRIS"
                />
            </div>

            <div class="pt-4 border-t border-zinc-100 dark:border-zinc-800 flex justify-end">
                <flux:button type="submit" variant="primary">
                    Simpan Perubahan
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
