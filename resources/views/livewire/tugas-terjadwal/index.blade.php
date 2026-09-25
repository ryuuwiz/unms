<div class="space-y-6">
    <div>
        <flux:heading size="xl">Log Tugas Terjadwal</flux:heading>
        <flux:subheading>
            Riwayat eksekusi tugas berkala sistem ({{ \App\Models\LogTugasTerjadwal::HARI_RETENSI }} hari terakhir).
            Tugas tiap menit atau lebih sering hanya tercatat saat gagal.
        </flux:subheading>
    </div>

    <!-- Ringkasan per tugas -->
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3">Tugas</th>
                        <th class="px-4 py-3">Log Terakhir</th>
                        <th class="px-4 py-3 text-right">Durasi</th>
                        <th class="px-4 py-3 text-center">Status Terakhir</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($ringkasan as $log)
                        <tr wire:key="ringkasan-{{ $log->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                            <td class="px-4 py-3 font-mono font-semibold text-zinc-900 dark:text-zinc-100">
                                <button type="button" wire:click="$set('perintah', @js($log->perintah))" class="hover:underline text-left">
                                    {{ $log->perintah }}
                                </button>
                            </td>
                            <td class="px-4 py-3">
                                {{ $log->selesai_at->translatedFormat('d M Y H:i:s') }}
                                <span class="text-[11px] text-zinc-400">({{ $log->selesai_at->diffForHumans() }})</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ $log->durasi_detik !== null ? $log->durasi_detik.' dtk' : '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" variant="pill" :color="$log->status->color()">{{ $log->status->label() }}</flux:badge>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-8 text-center text-zinc-400">Belum ada log tugas terjadwal.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </flux:card>

    <!-- Filter Bar -->
    <flux:card class="p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <flux:select wire:model.live="perintah" placeholder="Semua Tugas">
                <flux:select.option value="">Semua Tugas</flux:select.option>
                @foreach($ringkasan as $log)
                    <flux:select.option :value="$log->perintah">{{ $log->perintah }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select wire:model.live="status" placeholder="Semua Status">
                <flux:select.option value="">Semua Status</flux:select.option>
                @foreach($statuses as $s)
                    <flux:select.option :value="$s->value">{{ $s->label() }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </flux:card>

    <!-- Riwayat -->
    <flux:card class="p-0 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead class="bg-zinc-50 dark:bg-zinc-800 text-zinc-600 dark:text-zinc-300 font-semibold border-b border-zinc-200 dark:border-zinc-700">
                    <tr>
                        <th class="px-4 py-3">Selesai</th>
                        <th class="px-4 py-3">Tugas</th>
                        <th class="px-4 py-3 text-right">Durasi</th>
                        <th class="px-4 py-3 text-center">Exit Code</th>
                        <th class="px-4 py-3 text-center">Status</th>
                        <th class="px-4 py-3 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse($logs as $log)
                        <tr wire:key="log-{{ $log->id }}" class="hover:bg-zinc-50 dark:hover:bg-zinc-800/40">
                            <td class="px-4 py-3">{{ $log->selesai_at->translatedFormat('d M Y H:i:s') }}</td>
                            <td class="px-4 py-3 font-mono">{{ $log->perintah }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ $log->durasi_detik !== null ? $log->durasi_detik.' dtk' : '-' }}</td>
                            <td class="px-4 py-3 text-center font-mono">{{ $log->exit_code ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                <flux:badge size="sm" variant="pill" :color="$log->status->color()">{{ $log->status->label() }}</flux:badge>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if($log->output)
                                    <flux:button size="xs" variant="ghost" wire:click="$set('outputLogId', {{ $log->id }})">Output</flux:button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-zinc-400">Tidak ada log yang cocok dengan filter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="p-4 border-t border-zinc-100 dark:border-zinc-800">
                {{ $logs->links() }}
            </div>
        @endif
    </flux:card>

    <flux:modal :open="$outputLogId !== null" wire:model.self="outputLogId" class="w-full max-w-3xl">
        @if($this->outputLog)
            <div class="space-y-4">
                <div>
                    <flux:heading size="lg">Output {{ $this->outputLog->perintah }}</flux:heading>
                    <flux:subheading>{{ $this->outputLog->selesai_at->translatedFormat('d M Y H:i:s') }}</flux:subheading>
                </div>
                <pre class="text-xs font-mono whitespace-pre-wrap break-all bg-zinc-50 dark:bg-zinc-900 p-3 rounded-lg max-h-[60vh] overflow-y-auto">{{ $this->outputLog->output }}</pre>
            </div>
        @endif
    </flux:modal>
</div>
