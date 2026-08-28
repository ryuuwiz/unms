<div class="space-y-6">
    <div>
        <flux:button :href="route('portal.tiket.index')" wire:navigate variant="ghost" icon="arrow-left" size="sm" class="mb-3">
            Kembali ke Tiket Saya
        </flux:button>
        <h1 class="text-xl font-bold text-zinc-900 dark:text-zinc-100">Ajukan Tiket Baru</h1>
        <p class="text-xs text-zinc-500 dark:text-zinc-400 mt-0.5">Sampaikan keluhan atau permohonan layanan kepada tim kami</p>
    </div>

    {{-- Modal Konfirmasi Pencabutan --}}
    @if($showKonfirmasiPencabutan)
        <flux:card class="border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 p-6 space-y-4">
            <div class="flex gap-3">
                <flux:icon icon="exclamation-triangle" class="size-6 text-amber-500 shrink-0 mt-0.5" />
                <div class="space-y-1">
                    <p class="font-semibold text-amber-800 dark:text-amber-300">Konfirmasi Pengajuan Pencabutan Layanan</p>
                    <p class="text-sm text-amber-700 dark:text-amber-400">
                        Anda akan mengajukan tiket <strong>Pencabutan Layanan</strong>. Tim kami akan menghubungi Anda untuk konfirmasi sebelum proses pencabutan dilakukan. Layanan Anda tidak akan langsung dinonaktifkan.
                    </p>
                </div>
            </div>
            <div class="flex gap-2 justify-end">
                <flux:button wire:click="batalKonfirmasi" variant="ghost" size="sm">Batal</flux:button>
                <flux:button wire:click="simpan" variant="danger" size="sm" icon="check">Ya, Lanjutkan Pengajuan</flux:button>
            </div>
        </flux:card>
    @endif

    <flux:card class="p-6">
        <form wire:submit="submit" class="space-y-5">
            {{-- Jenis Tiket --}}
            <flux:field>
                <flux:label>Jenis Tiket</flux:label>
                <flux:select wire:model.live="jenis" id="jenis">
                    @foreach($jenisYangDiizinkan as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="jenis" />
            </flux:field>

            {{-- Pilih Layanan --}}
            <flux:field>
                <flux:label>Layanan yang Bermasalah / Terkait</flux:label>
                @if($layanans->isEmpty())
                    <flux:callout variant="warning" icon="exclamation-triangle">
                        Tidak ada layanan aktif atau suspend yang dapat dipilih.
                    </flux:callout>
                @else
                    <flux:select wire:model="layanan_pelanggan_id" id="layanan_pelanggan_id">
                        <flux:select.option value="">-- Pilih Layanan --</flux:select.option>
                        @foreach($layanans as $layanan)
                            <flux:select.option :value="$layanan->id">
                                {{ $layanan->site_id }}
                                @if($layanan->paketLayanan)
                                    &mdash; {{ $layanan->paketLayanan->nama_paket }}
                                @endif
                                ({{ $layanan->status->label() ?? $layanan->status }})
                            </flux:select.option>
                        @endforeach
                    </flux:select>
                @endif
                <flux:error name="layanan_pelanggan_id" />
            </flux:field>

            {{-- Deskripsi --}}
            <flux:field>
                <flux:label>Deskripsi Keluhan / Permohonan</flux:label>
                <flux:textarea
                    wire:model="deskripsi"
                    id="deskripsi"
                    rows="5"
                    placeholder="Jelaskan masalah atau permohonan Anda secara detail. Semakin lengkap informasi yang Anda berikan, semakin cepat tim kami dapat membantu."
                />
                <flux:error name="deskripsi" />
                <flux:description>Minimal 5 karakter, maksimal 3000 karakter.</flux:description>
            </flux:field>

            {{-- Foto Kendala (Opsional) --}}
            <flux:field>
                <flux:label>Foto Bukti / Kendala (Opsional)</flux:label>
                <input
                    type="file"
                    wire:model="fotoKendala"
                    accept="image/png, image/jpeg, image/webp"
                    class="block w-full text-xs text-zinc-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 dark:file:bg-zinc-700 dark:file:text-zinc-200"
                />
                <flux:description>Format: JPG, PNG, WEBP. Maksimal 5 MB.</flux:description>
                <flux:error name="fotoKendala" />
            </flux:field>

            @if ($fotoKendala)
                <div class="flex items-center gap-3 p-2 bg-zinc-50 dark:bg-zinc-900 rounded-lg border border-zinc-200 dark:border-zinc-700 text-xs">
                    <span class="text-emerald-600 font-medium">✓ Foto terpilih:</span>
                    <span class="text-zinc-600 dark:text-zinc-300 truncate">{{ $fotoKendala->getClientOriginalName() }}</span>
                </div>
            @endif

            <div class="flex justify-end pt-2">
                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    Ajukan Tiket
                </flux:button>
            </div>
        </form>
    </flux:card>
</div>
