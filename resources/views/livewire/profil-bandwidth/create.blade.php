<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah Profil Bandwidth</flux:heading>
        <flux:subheading>Konfigurasi parameter limit rate dan burst rate untuk di-provision ke RouterOS.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Nama Profil</flux:label>
            <flux:input wire:model="nama_bandwidth" placeholder="Contoh: 20Mbps-Dedicated" />
            <flux:description>Minimal 4 karakter, maksimal 30 karakter.</flux:description>
            <flux:error name="nama_bandwidth" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Max Limit Upload (TX) <span class="font-mono text-xs text-zinc-400">Mbps</span></flux:label>
                <flux:input wire:model.live.debounce.500ms="max_limit_tx" type="number" placeholder="Contoh: 20" />
                <flux:error name="max_limit_tx" />
            </flux:field>

            <flux:field>
                <flux:label>Max Limit Download (RX) <span class="font-mono text-xs text-zinc-400">Mbps</span></flux:label>
                <flux:input wire:model="max_limit_rx" type="number" placeholder="Contoh: 20" />
                <flux:error name="max_limit_rx" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Priority (1 = Tertinggi, 8 = Standar)</flux:label>
            <flux:input wire:model="priority" type="number" min="1" max="8" />
            <flux:error name="priority" />
        </flux:field>

        <flux:separator />

        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <flux:heading size="base">Konfigurasi Burst (Opsional)</flux:heading>
                    <flux:subheading>Aktifkan jika ingin memberikan burst speed pada awal koneksi.</flux:subheading>
                </div>
                <flux:switch wire:model.live="useBurst" />
            </div>

            @if ($useBurst)
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 p-4 rounded-xl border border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900/50">
                    <flux:field>
                        <flux:label>Burst Rate Upload (TX) <span class="font-mono text-xs text-zinc-400">Mbps</span></flux:label>
                        <flux:input wire:model="burst_rate_tx" type="number" placeholder="Contoh: 30" />
                        <flux:error name="burst_rate_tx" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Burst Rate Download (RX) <span class="font-mono text-xs text-zinc-400">Mbps</span></flux:label>
                        <flux:input wire:model="burst_rate_rx" type="number" placeholder="Contoh: 30" />
                        <flux:error name="burst_rate_rx" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Burst Threshold Upload (TX) <span class="font-mono text-xs text-zinc-400">Mbps</span></flux:label>
                        <flux:input wire:model="burst_threshold_tx" type="number" placeholder="Contoh: 15" />
                        <flux:error name="burst_threshold_tx" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Burst Threshold Download (RX) <span class="font-mono text-xs text-zinc-400">Mbps</span></flux:label>
                        <flux:input wire:model="burst_threshold_rx" type="number" placeholder="Contoh: 15" />
                        <flux:error name="burst_threshold_rx" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Burst Time TX <span class="font-mono text-xs text-zinc-400">Detik</span></flux:label>
                        <flux:input wire:model="burst_time_tx" type="number" placeholder="Contoh: 16" />
                        <flux:error name="burst_time_tx" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Burst Time RX <span class="font-mono text-xs text-zinc-400">Detik</span></flux:label>
                        <flux:input wire:model="burst_time_rx" type="number" placeholder="Contoh: 16" />
                        <flux:error name="burst_time_rx" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Limit Rate Upload (TX) <span class="font-mono text-xs text-zinc-400">Mbps (Opsional)</span></flux:label>
                        <flux:input wire:model="limit_rate_tx" type="number" placeholder="Contoh: 10" />
                        <flux:error name="limit_rate_tx" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Limit Rate Download (RX) <span class="font-mono text-xs text-zinc-400">Mbps (Opsional)</span></flux:label>
                        <flux:input wire:model="limit_rate_rx" type="number" placeholder="Contoh: 10" />
                        <flux:error name="limit_rate_rx" />
                    </flux:field>
                </div>
            @endif
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('profil-bandwidth.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan Profil</flux:button>
        </div>
    </form>
</div>
