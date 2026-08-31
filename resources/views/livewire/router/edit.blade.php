<div class="mx-auto max-w-4xl space-y-6" wire:poll.5s>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Edit Router</flux:heading>
            <flux:subheading>Perbarui konfigurasi koneksi router {{ $nama_router }}.</flux:subheading>
        </div>
        <div class="flex items-center gap-2">
            <flux:button wire:click="provisionFullRouter" wire:loading.attr="disabled" variant="subtle" icon="arrow-path-rounded-square" title="Jalankan pipeline provisi penuh (IP Pool, Profil, PPP Secret, Isolir)">
                <span wire:loading.remove wire:target="provisionFullRouter">Provisi Penuh Router</span>
                <span wire:loading wire:target="provisionFullRouter">Memproses Pipeline...</span>
            </flux:button>
            <flux:button wire:click="autoRecoverPpp" wire:loading.attr="disabled" variant="subtle" icon="arrow-path" title="Periksa dan pulihkan seluruh PPP secret di RouterOS yang belum lengkap">
                <span wire:loading.remove wire:target="autoRecoverPpp">Auto-Recover PPP</span>
                <span wire:loading wire:target="autoRecoverPpp">Memulihkan PPP...</span>
            </flux:button>
            <flux:button wire:click="testConnection" wire:loading.attr="disabled" variant="subtle" icon="bolt">
                <span wire:loading.remove wire:target="testConnection">Uji Koneksi</span>
                <span wire:loading wire:target="testConnection">Menguji...</span>
            </flux:button>
            <flux:badge size="md" :color="$router->status_koneksi->color()">
                {{ $router->status_koneksi->label() }}
            </flux:badge>
        </div>
    </div>

    {{-- Kartu Status & Metrik RouterOS --}}
    @if ($router->last_ping_at || $router->routeros_version)
        <div class="grid grid-cols-2 gap-4 rounded-xl border border-zinc-200 bg-zinc-50/50 p-4 sm:grid-cols-4 dark:border-zinc-800 dark:bg-zinc-900/50">
            <div>
                <span class="text-xs text-zinc-500">Versi RouterOS</span>
                <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $router->routeros_version ?: '-' }} ({{ $router->board_name ?: 'Unknown' }})</p>
            </div>
            <div>
                <span class="text-xs text-zinc-500">Beban CPU</span>
                <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $router->cpu_load !== null ? $router->cpu_load.'%' : '-' }}</p>
            </div>
            <div>
                <span class="text-xs text-zinc-500">Uptime</span>
                <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $router->uptime ?: '-' }}</p>
            </div>
            <div>
                <span class="text-xs text-zinc-500">Terakhir Diperiksa</span>
                <p class="font-medium text-zinc-800 dark:text-zinc-200">{{ $router->last_ping_at ? $router->last_ping_at->diffForHumans() : '-' }}</p>
            </div>
        </div>
    @endif

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        <flux:field>
            <flux:label>Nama Router</flux:label>
            <flux:input wire:model="nama_router" placeholder="Contoh: RB4011-Core" />
            <flux:error name="nama_router" />
        </flux:field>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <flux:field class="sm:col-span-2">
                <flux:label>Alamat IP / Hostname Router</flux:label>
                <flux:input wire:model="ip_address" placeholder="192.168.88.1 atau router.domain.com" />
                <flux:error name="ip_address" />
            </flux:field>

            <flux:field class="sm:col-span-1">
                <flux:label>Port API</flux:label>
                <flux:input wire:model="port" type="number" />
                <flux:error name="port" />
            </flux:field>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <flux:field>
                <flux:label>Username API</flux:label>
                <flux:input wire:model="username" />
                <flux:error name="username" />
            </flux:field>

            <flux:field>
                <flux:label>Password API <span class="text-zinc-400 font-normal">(isi jika ingin ubah)</span></flux:label>
                <flux:input wire:model="password" type="password" placeholder="Kosongkan jika tidak diubah" />
                <flux:error name="password" />
            </flux:field>
        </div>

        <flux:field>
            <flux:label>Deskripsi <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:textarea wire:model="deskripsi" rows="3" />
            <flux:error name="deskripsi" />
        </flux:field>

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('router.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Perbarui Router</flux:button>
        </div>
    </form>

    {{-- Riwayat Job Integrasi Router Ini --}}
    @if ($router->jobLogs->isNotEmpty())
        <div class="space-y-3 pt-6">
            <div>
                <flux:heading size="lg">Riwayat Log Router Ini</flux:heading>
            </div>
            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-800 text-xs">
                    <thead class="bg-zinc-50 dark:bg-zinc-900">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium text-zinc-500">Waktu</th>
                            <th class="px-4 py-2 text-left font-medium text-zinc-500">Job</th>
                            <th class="px-4 py-2 text-left font-medium text-zinc-500">Status</th>
                            <th class="px-4 py-2 text-left font-medium text-zinc-500">Detail / Pesan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-200 dark:divide-zinc-800 bg-white dark:bg-zinc-900">
                        @foreach ($router->jobLogs as $log)
                            <tr>
                                <td class="px-4 py-2 text-zinc-500 whitespace-nowrap">{{ $log->created_at->diffForHumans() }}</td>
                                <td class="px-4 py-2 font-medium">{{ $log->job_type->label() }}</td>
                                <td class="px-4 py-2">
                                    <flux:badge size="sm" :color="$log->status->color()">{{ $log->status->label() }}</flux:badge>
                                </td>
                                <td class="px-4 py-2 text-zinc-600 dark:text-zinc-400 truncate max-w-xs">{{ $log->error_message ?: 'Sukses' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
