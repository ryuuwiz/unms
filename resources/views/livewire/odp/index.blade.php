<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Data ODP</flux:heading>
            <flux:subheading>Kelola titik sebaran infrastruktur Optical Distribution Point dan area cakupan jaringan.</flux:subheading>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <flux:button :href="route('maps.index')" wire:navigate variant="subtle" icon="map">
                Peta Jaringan
            </flux:button>
            <flux:button wire:click="openImportModal" variant="subtle" icon="arrow-up-tray">
                Import KML / GeoJSON
            </flux:button>
            @can('create', App\Models\Odp::class)
                <flux:button :href="route('odp.create')" wire:navigate variant="primary" icon="plus">
                    Tambah ODP
                </flux:button>
            @endcan
        </div>
    </div>

    {{-- Filter & Search Bar --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.300ms="search"
                icon="magnifying-glass"
                placeholder="Cari nama ODP, keterangan, atau titik koordinat..."
                clearable
            />
        </div>
    </div>

    {{-- Bilah pilihan & hapus massal --}}
    @can('odp.hapus')
        @if ($jumlahDipilih > 0)
            <div class="flex flex-col gap-2 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-rose-900 dark:bg-rose-950/40">
                <div class="text-zinc-700 dark:text-zinc-300">
                    <strong>{{ number_format($jumlahDipilih, 0, ',', '.') }}</strong> ODP dipilih{{ $pilihSemuaHasil ? ' (semua hasil pencarian)' : '' }}.
                    @if (! $pilihSemuaHasil && $idsHalaman !== [] && array_diff($idsHalaman, $dipilih) === [] && $odps->total() > count($idsHalaman))
                        <button type="button" wire:click="pilihSemua" class="ms-1 font-medium text-primary-600 hover:underline dark:text-primary-400">
                            Pilih semua {{ number_format($odps->total(), 0, ',', '.') }} hasil pencarian
                        </button>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <flux:button size="sm" variant="ghost" wire:click="batalPilih">Batal</flux:button>
                    <flux:button size="sm" variant="danger" icon="trash" wire:click="bukaHapusMassal">Hapus terpilih ({{ number_format($jumlahDipilih, 0, ',', '.') }})</flux:button>
                </div>
            </div>
        @endif
    @endcan

    {{-- Tabel ODP --}}
    <flux:table>
        <flux:table.columns>
            @can('odp.hapus')
                <flux:table.column class="w-8">
                    <flux:checkbox
                        :checked="$idsHalaman !== [] && ($pilihSemuaHasil || array_diff($idsHalaman, $dipilih) === [])"
                        wire:click="pilihHalaman(@js($idsHalaman))"
                        aria-label="Pilih semua di halaman ini"
                    />
                </flux:table.column>
            @endcan
            <flux:table.column>Nama ODP</flux:table.column>
            <flux:table.column>Kapasitas</flux:table.column>
            <flux:table.column>Titik Koordinat</flux:table.column>
            <flux:table.column>Keterangan</flux:table.column>
            <flux:table.column align="end">Aksi</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($odps as $odp)
                <flux:table.row :key="$odp->id">
                    @can('odp.hapus')
                        <flux:table.cell>
                            @if ($pilihSemuaHasil)
                                <flux:checkbox checked disabled aria-label="Terpilih" />
                            @else
                                <flux:checkbox wire:model.live="dipilih" value="{{ $odp->id }}" aria-label="Pilih {{ $odp->nama_odp }}" />
                            @endif
                        </flux:table.cell>
                    @endcan
                    <flux:table.cell class="font-medium text-zinc-900 dark:text-zinc-100">
                        <div class="flex flex-col">
                            <a href="{{ route('odp.show', $odp) }}" wire:navigate class="hover:text-primary-600 font-semibold flex items-center gap-1.5">
                                <flux:icon name="circle-stack" class="size-4 text-sky-500" />
                                {{ $odp->nama_odp }}
                            </a>
                            @if ($odp->perumahan)
                                <span class="text-xs text-zinc-400 mt-0.5">{{ $odp->perumahan->nama_perumahan }}</span>
                            @endif
                        </div>
                    </flux:table.cell>

                    <flux:table.cell>
                        <flux:badge size="sm" color="sky">
                            {{ $odp->kapasitas_port }} Port
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell>
                        @if (is_numeric($odp->latitude) && is_numeric($odp->longitude))
                            <a
                                href="{{ route('maps.index') }}"
                                wire:navigate
                                class="inline-flex items-center gap-1 font-mono text-xs text-emerald-600 dark:text-emerald-400 hover:underline"
                            >
                                <flux:icon name="map-pin" class="size-3.5" />
                                {{ number_format((float) $odp->latitude, 6) }}, {{ number_format((float) $odp->longitude, 6) }}
                            </a>
                        @else
                            <span class="text-xs text-zinc-400 italic">Belum diatur</span>
                        @endif
                    </flux:table.cell>

                    <flux:table.cell class="text-xs text-zinc-500">
                        {{ $odp->keterangan ?: '—' }}
                    </flux:table.cell>

                    <flux:table.cell align="end">
                        <div class="flex items-center justify-end gap-1">
                            <flux:button :href="route('odp.show', $odp)" wire:navigate size="xs" variant="ghost" icon="eye">
                                Detail
                            </flux:button>
                            @can('update', $odp)
                                <flux:button :href="route('odp.edit', $odp)" wire:navigate size="xs" variant="ghost" icon="pencil-square">
                                    Edit
                                </flux:button>
                            @endcan
                            @can('delete', $odp)
                                <flux:button
                                    wire:click="deleteOdp({{ $odp->id }})"
                                    wire:confirm="Yakin ingin menghapus titik ODP ini?"
                                    size="xs"
                                    variant="ghost"
                                    icon="trash"
                                    class="text-rose-600 hover:text-rose-700"
                                />
                            @endcan
                        </div>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="6" class="py-12 text-center text-zinc-400">
                        <flux:icon name="circle-stack" class="mx-auto size-8 text-zinc-300 dark:text-zinc-600" />
                        <div class="mt-2 text-sm">Tidak ada data ODP ditemukan.</div>
                    </flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    @if ($odps->hasPages())
        <div class="mt-4">
            {{ $odps->links() }}
        </div>
    @endif

    {{-- Modal Konfirmasi Hapus Massal --}}
    <flux:modal wire:model="showHapusModal" class="max-w-md">
        <form wire:submit="hapusMassal" class="space-y-5">
            <div>
                <flux:heading size="lg">Hapus {{ number_format($jumlahDipilih, 0, ',', '.') }} ODP?</flux:heading>
                <flux:subheading>Penghapusan permanen beserta port-nya. ODP yang port-nya masih dipakai layanan pelanggan akan dilewati otomatis.</flux:subheading>
            </div>

            @if ($jumlahDipilih > $batasTanpaKetik)
                <flux:input wire:model="konfirmasiHapus" label="Ketik HAPUS untuk melanjutkan" autocomplete="off" />
                <flux:error name="konfirmasiHapus" />
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="filled">Batal</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger" icon="trash">Hapus</flux:button>
            </div>
        </form>
    </flux:modal>

    {{-- Modal Import KML / KMZ / GeoJSON --}}
    <flux:modal wire:model="showImportModal" class="max-w-3xl">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Import ODP Otomatis (KML / KMZ / GeoJSON)</flux:heading>
                <flux:subheading>Cukup unggah berkas .kml/.kmz dari Google Earth atau .geojson dari QGIS. Sistem akan otomatis mengekstrak titik sebaran ODP dan polygon area cakupan.</flux:subheading>
            </div>

            <div class="space-y-4">
                {{-- Input File --}}
                <flux:field>
                    <flux:label>Pilih atau Tarik Berkas KML / KMZ / GeoJSON</flux:label>
                    <input
                        type="file"
                        wire:model="importFile"
                        accept=".kml,.kmz,.geojson,.json"
                        class="w-full text-sm text-zinc-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 dark:file:bg-primary-950 dark:file:text-primary-300 border border-dashed border-zinc-300 dark:border-zinc-700 rounded-xl p-3"
                    />
                    <flux:description>Mendukung format Google Earth (.kml / .kmz Placemark) dan QGIS (.geojson FeatureCollection).</flux:description>
                    <flux:error name="importFile" />
                </flux:field>

                {{-- Preview Section --}}
                @if (!empty($parsedOdps) || !empty($parsedPolygons))
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-semibold text-zinc-700 dark:text-zinc-300">
                                Titik ODP Terdeteksi ({{ count($parsedOdps) }} ODP):
                            </span>
                            @if (!empty($parsedPolygons))
                                <flux:badge size="sm" color="indigo">{{ count($parsedPolygons) }} Coverage Polygon</flux:badge>
                            @endif
                        </div>

                        <div class="max-h-60 overflow-y-auto rounded-lg border border-zinc-200 bg-zinc-50 p-2 dark:border-zinc-700 dark:bg-zinc-900 text-xs">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="border-b border-zinc-200 text-zinc-400 dark:border-zinc-700">
                                        <th class="py-1.5 px-2">Nama ODP</th>
                                        <th class="py-1.5 px-2">Kapasitas</th>
                                        <th class="py-1.5 px-2">Koordinat</th>
                                        <th class="py-1.5 px-2">Keterangan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (array_slice($parsedOdps, 0, 25) as $pt)
                                        <tr class="border-b border-zinc-100 dark:border-zinc-800 hover:bg-zinc-100/60 dark:hover:bg-zinc-800/60">
                                            <td class="py-1.5 px-2 font-medium text-zinc-900 dark:text-zinc-100">{{ $pt['nama'] }}</td>
                                            <td class="py-1.5 px-2">
                                                <flux:badge size="sm" color="sky">{{ $pt['kapasitas'] }} Port</flux:badge>
                                            </td>
                                            <td class="py-1.5 px-2 font-mono text-[11px] text-zinc-600 dark:text-zinc-400">
                                                {{ number_format($pt['latitude'], 6) }}, {{ number_format($pt['longitude'], 6) }}
                                            </td>
                                            <td class="py-1.5 px-2 text-zinc-500 truncate max-w-[160px]">
                                                {{ $pt['keterangan'] ?: '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if (count($parsedOdps) > 25)
                                <div class="py-2 text-center text-zinc-400 italic text-[11px]">
                                    ... dan {{ count($parsedOdps) - 25 }} titik ODP lainnya
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-zinc-200 dark:border-zinc-700">
                <flux:button wire:click="closeImportModal" variant="ghost">Batal</flux:button>
                <flux:button
                    type="button"
                    wire:click="executeImport"
                    variant="primary"
                    icon="check"
                    :disabled="empty($parsedOdps) && empty($parsedPolygons)"
                >
                    Impor Sekarang ({{ count($parsedOdps) }} ODP)
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
