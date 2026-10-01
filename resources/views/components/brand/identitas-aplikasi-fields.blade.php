@props(['namaBrand' => '', 'ikonPreview' => null])

<div class="space-y-4 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4">
    <div>
        <flux:heading size="sm">Identitas Aplikasi Pelanggan</flux:heading>
        <flux:subheading>Dipakai Portal dan aplikasi yang dipasang pelanggan di layar utama ponsel. Kosongkan untuk diturunkan dari brand ini.</flux:subheading>
    </div>

    <flux:input
        wire:model="nama_pendek"
        label="Nama Pendek (opsional)"
        :placeholder="mb_substr($namaBrand, 0, \App\Support\BrandPelanggan::PANJANG_NAMA_PENDEK)"
        maxlength="{{ \App\Support\BrandPelanggan::PANJANG_NAMA_PENDEK }}"
        description="Label di bawah ikon aplikasi, maks. {{ \App\Support\BrandPelanggan::PANJANG_NAMA_PENDEK }} karakter."
    />

    <flux:field>
        <flux:label>Warna Utama (opsional)</flux:label>
        <div class="flex items-center gap-3" x-data>
            <input type="color" class="h-10 w-14 cursor-pointer rounded-lg border border-zinc-200 dark:border-zinc-700 bg-transparent"
                :value="$wire.warna_utama || '{{ \App\Support\BrandPelanggan::WARNA_DEFAULT }}'"
                x-on:input="$wire.warna_utama = $event.target.value" />
            <flux:input wire:model.live="warna_utama" placeholder="{{ \App\Support\BrandPelanggan::WARNA_DEFAULT }}" class="max-w-36 font-mono" />
            <span class="rounded-lg px-3 py-2 text-xs font-semibold text-white"
                :style="'background-color: ' + ($wire.warna_utama || '{{ \App\Support\BrandPelanggan::WARNA_DEFAULT }}')">
                Pratinjau
            </span>
        </div>
        <flux:description>Warna tombol, aksen, dan status bar aplikasi.</flux:description>
        <flux:error name="warna_utama" />
    </flux:field>

    <flux:field>
        <flux:label>Ikon Aplikasi (opsional)</flux:label>
        <div class="flex items-center gap-4">
            <x-file-upload-preview :src="$ikonPreview" target="ikon_aplikasi"
                aspect="square" fit="cover" :deletable="(bool) $ikonPreview"
                delete-action="hapusIkonAplikasi"
                delete-confirm="Hapus ikon aplikasi ini?"
                delete-label="Hapus Ikon" icon="device-phone-mobile" empty-text="Dari logo" alt="Preview Ikon Aplikasi" />
            <input type="file" wire:model="ikon_aplikasi" accept="image/png,image/jpeg,image/webp"
                class="block w-full text-sm text-zinc-600 dark:text-zinc-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-zinc-100 file:text-zinc-700 dark:file:bg-zinc-800 dark:file:text-zinc-300 cursor-pointer" />
        </div>
        <flux:description>Gambar persegi minimal 512×512 (PNG, JPG, WEBP). Kosongkan untuk dibuat dari logo brand.</flux:description>
        <flux:error name="ikon_aplikasi" />
    </flux:field>
</div>
