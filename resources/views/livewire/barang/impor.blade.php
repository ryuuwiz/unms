<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="xl">Impor Inventaris</flux:heading>
            <flux:subheading>Migrasi awal data gudang dari satu berkas Excel berisi sheet Data Barang, Barang Masuk, dan Barang Keluar.</flux:subheading>
        </div>
        <flux:button icon="document-arrow-down" wire:click="unduhTemplate">Unduh Template</flux:button>
    </div>

    <flux:card class="space-y-4 p-6">
        <flux:callout icon="information-circle">
            <flux:callout.text>
                Impor hanya bisa dilakukan sekali, sebelum ada mutasi barang di sistem. Data disimpan sekaligus hanya bila tidak ada galat.
                Barang dilacak per unit (modem) ditulis satu baris per kode unit (mis. MDM-NEW-BF-240); stok awalnya lewat sheet Barang Masuk dengan TIPE "SALDO AWAL".
            </flux:callout.text>
        </flux:callout>

        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <flux:input type="file" wire:model="berkas" label="Berkas Excel (.xlsx, maks. 5 MB)" accept=".xlsx,.xls" class="flex-1" />
            <flux:button wire:click="pratinjau" wire:loading.attr="disabled" wire:target="berkas,pratinjau" icon="magnifying-glass">Pratinjau</flux:button>
        </div>
        <flux:error name="berkas" />
    </flux:card>

    @if ($hasil)
        <flux:card class="space-y-4 p-6">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="text-sm text-zinc-600 dark:text-zinc-400">
                    Data Barang: <strong>{{ $hasil['jumlah']['barang'] }}</strong> baris ·
                    Barang Masuk: <strong>{{ $hasil['jumlah']['masuk'] }}</strong> baris ·
                    Barang Keluar: <strong>{{ $hasil['jumlah']['keluar'] }}</strong> baris
                </div>
                @if ($hasil['disimpan'])
                    <flux:badge color="green" icon="check-circle">Tersimpan</flux:badge>
                @elseif ($hasil['bisa_disimpan'])
                    <flux:button variant="primary" icon="arrow-down-tray" wire:click="simpan" wire:loading.attr="disabled" wire:confirm="Simpan seluruh data impor? Proses ini hanya bisa dilakukan sekali.">Simpan Impor</flux:button>
                @else
                    <flux:badge color="red" icon="x-circle">Perbaiki galat lalu pratinjau ulang</flux:badge>
                @endif
            </div>

            @if (($hasil['teknisi_tak_dikenal'] ?? []) !== [])
                <div class="space-y-3 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40">
                    <div class="text-sm font-medium text-amber-800 dark:text-amber-300">Nama teknisi berikut tidak cocok dengan user teknisi. Petakan lalu klik Pratinjau lagi.</div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        @foreach ($hasil['teknisi_tak_dikenal'] as $namaBerkas)
                            <flux:select wire:model="petaTeknisi.{{ $namaBerkas }}" :label="'“'.strtoupper($namaBerkas).'” di berkas'" placeholder="Pilih teknisi...">
                                <flux:select.option value="">Pilih teknisi...</flux:select.option>
                                @foreach ($teknisis as $teknisi)
                                    <flux:select.option value="{{ $teknisi->id }}">{{ $teknisi->name }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endforeach
                    </div>
                </div>
            @endif

            @foreach ($hasil['galat_umum'] as $pesan)
                <flux:callout variant="danger" icon="x-circle"><flux:callout.text>{{ $pesan }}</flux:callout.text></flux:callout>
            @endforeach

            @foreach ($hasil['masalah'] as $sheet => $baris)
                <div>
                    <flux:heading size="sm" class="mb-2">{{ $sheet }}</flux:heading>
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>Baris</flux:table.column>
                            <flux:table.column>Data</flux:table.column>
                            <flux:table.column>Masalah</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($baris as $nomor => $m)
                                <flux:table.row :key="$sheet.'-'.$nomor">
                                    <flux:table.cell>{{ $nomor }}</flux:table.cell>
                                    <flux:table.cell><span class="font-mono text-xs">{{ $m['label'] }}</span></flux:table.cell>
                                    <flux:table.cell>
                                        @foreach ($m['galat'] as $pesan)
                                            <div class="text-sm text-rose-600 dark:text-rose-400">✕ {{ $pesan }}</div>
                                        @endforeach
                                        @foreach ($m['peringatan'] as $pesan)
                                            <div class="text-sm text-amber-600 dark:text-amber-400">! {{ $pesan }}</div>
                                        @endforeach
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                </div>
            @endforeach

            @if ($hasil['masalah'] === [] && $hasil['galat_umum'] === [] && ! $hasil['disimpan'])
                <div class="text-sm text-emerald-600 dark:text-emerald-400">Semua baris valid.</div>
            @endif
        </flux:card>
    @endif
</div>
