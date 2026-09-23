<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Media Library</flux:heading>
            <flux:subheading>Semua berkas media yang diunggah ke sistem (foto tiket, logo perusahaan, foto profil, berkas umum, dll). Dokumen pribadi pelanggan (KTP/dokumen legalitas) tidak ditampilkan di sini -- lihat halaman Detail Pelanggan.</flux:subheading>
        </div>
        <flux:button wire:click="runCheck" variant="ghost" icon="arrow-path" wire:loading.attr="disabled" wire:target="runCheck">
            <span wire:loading.remove wire:target="runCheck">Cek Ulang Storage</span>
            <span wire:loading wire:target="runCheck">Memeriksa...</span>
        </flux:button>
    </div>

    {{-- Status Koneksi S3 --}}
    <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
        <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
            <flux:icon name="cloud" class="size-5 text-blue-600 dark:text-blue-400" />
            Status Koneksi Storage
        </h3>

        @if (! $healthResult->applicable)
            <div class="p-4 rounded-lg bg-zinc-50 dark:bg-zinc-900/50 border border-zinc-200 dark:border-zinc-700 text-sm text-zinc-600 dark:text-zinc-400">
                Tidak berlaku — disk aktif saat ini: <strong class="font-mono">{{ $healthResult->diskName }}</strong> (bukan S3). Monitoring ini hanya relevan saat <code class="font-mono">FILESYSTEM_DISK=s3</code>.
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg">
                    <span class="text-zinc-500 block text-xs mb-1">Bucket</span>
                    <span class="font-mono font-semibold text-zinc-900 dark:text-white">{{ $healthResult->bucket ?? '-' }}</span>
                </div>
                <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg">
                    <span class="text-zinc-500 block text-xs mb-1">Bucket Bisa Diakses</span>
                    @if ($healthResult->bucketOk === true)
                        <flux:badge color="emerald" size="sm">OK</flux:badge>
                    @elseif ($healthResult->bucketOk === false)
                        <flux:badge color="rose" size="sm">Gagal</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">-</flux:badge>
                    @endif
                </div>
                <div class="p-3.5 bg-zinc-50 dark:bg-zinc-900/50 rounded-lg">
                    <span class="text-zinc-500 block text-xs mb-1">URL Publik Bisa Diakses</span>
                    @if ($healthResult->publicUrlOk === true)
                        <flux:badge color="emerald" size="sm">OK</flux:badge>
                    @elseif ($healthResult->publicUrlOk === false)
                        <flux:badge color="rose" size="sm">Gagal</flux:badge>
                    @else
                        <flux:badge color="zinc" size="sm">-</flux:badge>
                    @endif
                </div>
            </div>

            @if ($healthResult->errorMessage)
                <div class="p-3 rounded-lg bg-rose-50 dark:bg-rose-950/30 text-rose-700 dark:text-rose-300 text-xs">
                    {{ $healthResult->errorMessage }}
                </div>
            @endif

            <p class="text-xs text-zinc-400">Terakhir dicek: {{ $healthResult->checkedAt ?? '-' }}</p>
        @endif
    </div>

    {{-- Statistik --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        <x-stat-card title="Total Berkas Media" :value="number_format($totalCount)" icon="photo" color="indigo" />
        <x-stat-card title="Total Ukuran" :value="\Illuminate\Support\Number::fileSize($totalSize, precision: 1)" icon="circle-stack" color="cyan" />
    </div>

    <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
        <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
            <flux:icon name="chart-bar" class="size-5 text-emerald-600 dark:text-emerald-400" />
            Pemakaian per Collection
        </h3>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>Model</flux:table.column>
                <flux:table.column>Collection</flux:table.column>
                <flux:table.column>Jumlah Berkas</flux:table.column>
                <flux:table.column>Total Ukuran</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($perCollection as $row)
                    <flux:table.row>
                        <flux:table.cell>{{ class_basename($row->model_type) }}</flux:table.cell>
                        <flux:table.cell class="font-mono text-xs">{{ $row->collection_name }}</flux:table.cell>
                        <flux:table.cell>{{ number_format($row->jumlah) }}</flux:table.cell>
                        <flux:table.cell>{{ \Illuminate\Support\Number::fileSize((int) $row->total_ukuran, precision: 1) }}</flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row>
                        <flux:table.cell colspan="4" class="text-center text-zinc-400 italic">Belum ada media tersimpan.</flux:table.cell>
                    </flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>

    {{-- Unggah Berkas --}}
    @can('media_library.unggah')
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
            <h3 class="font-bold text-base text-zinc-900 dark:text-white flex items-center gap-2">
                <flux:icon name="arrow-up-tray" class="size-5 text-indigo-600 dark:text-indigo-400" />
                Unggah Berkas
            </h3>

            <form wire:submit="uploadFiles" class="space-y-3">
                <flux:input type="file" wire:model="uploads" multiple accept=".jpg,.jpeg,.png,.webp,.pdf,.xlsx,.xls,.csv,.docx" />
                <p class="text-xs text-zinc-400">Gambar (jpg, png, webp) atau dokumen (pdf, xlsx, xls, csv, docx), maksimal 20MB per berkas.</p>

                @error('uploads.*')
                    <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                @enderror

                <div wire:loading wire:target="uploads" class="text-xs text-zinc-400">Mengunggah...</div>

                <flux:button type="submit" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="uploadFiles">
                    Unggah
                </flux:button>
            </form>
        </div>
    @endcan

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <flux:input wire:model.live.debounce.400ms="search" placeholder="Cari nama berkas..." icon="magnifying-glass" class="sm:max-w-xs" />
        <flux:select wire:model.live="collectionFilter" placeholder="Semua collection" class="sm:max-w-xs">
            <flux:select.option value="">-- Semua Collection --</flux:select.option>
            @foreach ($collections as $collection)
                <flux:select.option value="{{ $collection }}">{{ $collection }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
        @forelse ($media as $item)
            <div class="group relative bg-white dark:bg-zinc-800 rounded-xl border border-zinc-200 dark:border-zinc-700 overflow-hidden shadow-sm">
                <a href="{{ $item->getUrl() }}" target="_blank" class="block aspect-square bg-zinc-100 dark:bg-zinc-900 flex items-center justify-center">
                    @if (str_starts_with((string) $item->mime_type, 'image/'))
                        <img src="{{ $item->getUrl() }}" class="h-full w-full object-cover" loading="lazy" />
                    @else
                        <flux:icon name="document" class="size-10 text-zinc-400" />
                    @endif
                </a>
                <div class="p-2.5 space-y-1 text-xs">
                    <p class="font-medium text-zinc-800 dark:text-zinc-200 truncate" title="{{ $item->file_name }}">{{ $item->file_name }}</p>
                    <p class="text-zinc-500 truncate">{{ class_basename($item->model_type) }} #{{ $item->model_id }} &bull; {{ $item->collection_name }}</p>
                    <div class="flex items-center justify-between">
                        <span class="text-zinc-400">{{ \Illuminate\Support\Number::fileSize($item->size, precision: 1) }}</span>
                        @can('media_library.hapus')
                            <flux:button size="xs" variant="ghost" icon="trash" class="text-rose-600 dark:text-rose-400"
                                wire:click="deleteMedia({{ $item->id }})"
                                wire:confirm="Hapus berkas {{ $item->file_name }}? Tindakan ini tidak bisa dibatalkan."
                            />
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 text-zinc-400 italic">
                Tidak ada berkas media yang cocok.
            </div>
        @endforelse
    </div>

    {{ $media->links() }}
</div>
