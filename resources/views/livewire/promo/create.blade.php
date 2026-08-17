<div class="max-w-3xl mx-auto">
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Tambah Program Promo Baru</h1>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mt-1">Buat kode kupon diskon atau bonus masa aktif tagihan pelanggan.</p>
        </div>
        <flux:button href="{{ route('promo.index') }}" variant="subtle" icon="arrow-left" wire:navigate>
            Kembali ke Daftar
        </flux:button>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="bg-white dark:bg-zinc-800 p-6 rounded-xl border border-zinc-200 dark:border-zinc-700 shadow-sm space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <flux:input wire:model="kode_promo" label="Kode Promo (Kapital) *" placeholder="Contoh: MERDEKA50, PROMOHEMAT" />
                    <flux:error name="kode_promo" />
                </div>
                <div>
                    <flux:input wire:model="nama_promo" label="Nama Promo *" placeholder="Contoh: Diskon Kemerdekaan 50rb" />
                    <flux:error name="nama_promo" />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <flux:select wire:model.live="jenis" label="Jenis Promo *">
                        <flux:select.option value="diskon">Diskon Potongan Harga</flux:select.option>
                        <flux:select.option value="bonus_durasi">Bonus Perpanjangan Durasi</flux:select.option>
                    </flux:select>
                </div>

                @if($jenis === 'diskon')
                    <div>
                        <flux:select wire:model.live="diskon_tipe" label="Bentuk Diskon *">
                            <flux:select.option value="nominal">Nominal Langsung (Rp)</flux:select.option>
                            <flux:select.option value="persentase">Persentase (%)</flux:select.option>
                        </flux:select>
                    </div>
                @endif
            </div>

            @if($jenis === 'diskon')
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:input type="number" wire:model="diskon_nilai" label="{{ $diskon_tipe === 'persentase' ? 'Besaran Diskon (%) *' : 'Nominal Potongan (Rp) *' }}" />
                        <flux:error name="diskon_nilai" />
                    </div>
                    <div>
                        <flux:input type="number" wire:model="minimal_nominal_invoice" label="Minimal Nominal Tagihan (Rp)" placeholder="0 jika tanpa minimal" />
                    </div>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <flux:input type="number" wire:model="bonus_bulan" label="Jumlah Bonus Bulan Masa Aktif *" placeholder="Contoh: 1" />
                        <flux:error name="bonus_bulan" />
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 pt-2 border-t border-zinc-200 dark:border-zinc-700">
                <div>
                    <flux:input type="number" wire:model="kuota_global" label="Batas Kuota Pemakaian (Total)" placeholder="Kosongkan jika unlimited" />
                </div>
                <div>
                    <flux:input type="date" wire:model="berlaku_dari" label="Berlaku Mulai Tanggal" />
                </div>
                <div>
                    <flux:input type="date" wire:model="berlaku_sampai" label="Berlaku Sampai Tanggal" />
                    <flux:error name="berlaku_sampai" />
                </div>
            </div>

            <div>
                <flux:textarea wire:model="deskripsi" label="Deskripsi / Keterangan Singkat" rows="2" placeholder="Catatan internal atau syarat ketentuan promo..." />
            </div>

            <div class="pt-2">
                <flux:switch wire:model="aktif" label="Status Aktif (Dapat digunakan saat terbit invoice)" />
            </div>
        </div>

        <div class="flex justify-end gap-3">
            <flux:button href="{{ route('promo.index') }}" variant="subtle" wire:navigate>
                Batal
            </flux:button>
            <flux:button type="submit" variant="primary" icon="check">
                Simpan Promo
            </flux:button>
        </div>
    </form>
</div>
