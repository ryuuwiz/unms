<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profil Perusahaan')" :subheading="__(
        'Atur identitas perusahaan, logo, kontak, rekening bank, dan catatan resmi untuk kop tagihan (invoice) & portal pelanggan.',
    )">
        <form wire:submit="save" enctype="multipart/form-data" class="my-6 w-full max-w-4xl space-y-8">

            {{-- Error Summary Banner --}}
            @if ($errors->any())
                <div
                    class="p-4 rounded-xl bg-red-50 dark:bg-red-950/50 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-300 text-sm space-y-1">
                    <div class="font-bold flex items-center gap-1.5">
                        <flux:icon name="exclamation-circle" class="size-4" />
                        Terdapat kesalahan pada formulir:
                    </div>
                    <ul class="list-disc list-inside text-xs space-y-0.5 ml-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- 1. Identitas & Logo --}}
            <flux:card class="p-6 space-y-6">
                <div>
                    <flux:heading size="lg">Identitas & Logo Perusahaan</flux:heading>
                    <flux:subheading>Nama brand dan logo akan ditampilkan pada kop invoice, portal pelanggan, dan header
                        aplikasi.</flux:subheading>
                </div>

                {{-- Logo Upload & Preview --}}
                <div
                    class="p-4 rounded-xl border border-dashed border-zinc-300 dark:border-zinc-700 bg-zinc-50/70 dark:bg-zinc-900/50 space-y-3">
                    <flux:label>Logo Perusahaan</flux:label>

                    <div class="flex flex-col sm:flex-row items-center gap-6">
                        <x-file-upload-preview :src="$logo?->temporaryUrl() ?: $existing_logo_url" target="logo"
                            aspect="square" fit="contain" :deletable="(bool) ($existing_logo_url || $logo)"
                            delete-action="hapusLogo"
                            delete-confirm="Apakah Anda yakin ingin menghapus logo perusahaan?"
                            delete-label="Hapus Logo" icon="photo" empty-text="Belum ada logo" alt="Preview Logo" />

                        <div class="flex-1 space-y-3">
                            <div>
                                <input type="file" wire:model="logo" id="company_logo_input"
                                    accept="image/png,image/jpeg,image/svg+xml,image/webp"
                                    class="block w-full text-sm text-zinc-600 dark:text-zinc-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-indigo-50 file:text-indigo-700 dark:file:bg-indigo-950/70 dark:file:text-indigo-300 hover:file:bg-indigo-100 dark:hover:file:bg-indigo-900/50 cursor-pointer" />
                                <flux:error name="logo" class="mt-1" />
                            </div>

                            <p class="text-xs text-zinc-500 dark:text-zinc-400 leading-relaxed">
                                Format: <strong>PNG, JPG, SVG, WEBP</strong> (Maks. 2MB). Rekomendasi: Logo transparan
                                rasio horizontal (contoh: 400x120px).
                            </p>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Nama Perusahaan (Badan Usaha / PT / CV) <span class="text-red-500">*</span>
                        </flux:label>
                        <flux:input wire:model="nama_perusahaan" placeholder="Contoh: PT GOBILLING NUSANTARA TEKNOLOGI"
                            required />
                        <flux:error name="nama_perusahaan" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Nama Brand / Display Name <span class="text-red-500">*</span></flux:label>
                        <flux:input wire:model="nama_brand" placeholder="Contoh: GOBILLING" required />
                        <flux:error name="nama_brand" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>Tagline / Motto Layanan</flux:label>
                    <flux:input wire:model="tagline" placeholder="Contoh: Solusi Billing & Manajemen ISP Terpadu" />
                    <flux:error name="tagline" />
                </flux:field>
            </flux:card>

            {{-- 2. Kontak & Lokasi --}}
            <flux:card class="p-6 space-y-6">
                <div>
                    <flux:heading size="lg">Kontak & Alamat Kantor</flux:heading>
                    <flux:subheading>Informasi kontak resmi yang dicantumkan pada invoice dan informasi bantuan
                        pelanggan.</flux:subheading>
                </div>

                <flux:field>
                    <flux:label>Alamat Lengkap Kantor</flux:label>
                    <flux:textarea wire:model="alamat" placeholder="Jl. Nama Jalan No. XX, Gedung / Kawasan"
                        rows="2" />
                    <flux:error name="alamat" />
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Kota / Kabupaten</flux:label>
                        <flux:input wire:model="kota" placeholder="Contoh: Jakarta Selatan" />
                        <flux:error name="kota" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Kode Pos</flux:label>
                        <flux:input wire:model="kode_pos" placeholder="Contoh: 12930" />
                        <flux:error name="kode_pos" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>No. Telepon Kantor</flux:label>
                        <flux:input wire:model="telepon" placeholder="Contoh: 021-5551234" />
                        <flux:error name="telepon" />
                    </flux:field>

                    <flux:field>
                        <flux:label>No. WhatsApp Layanan / Helpdesk</flux:label>
                        <flux:input wire:model="whatsapp" placeholder="Contoh: 0812-3456-7890" />
                        <flux:error name="whatsapp" />
                    </flux:field>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Email Resmi Perusahaan</flux:label>
                        <flux:input wire:model="email" type="email" placeholder="billing@gobilling.id" />
                        <flux:error name="email" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Website Resmi</flux:label>
                        <flux:input wire:model="website" placeholder="https://gobilling.id" />
                        <flux:error name="website" />
                    </flux:field>
                </div>
            </flux:card>

            {{-- 3. Rekening Pembayaran & Legalitas --}}
            <flux:card class="p-6 space-y-6">
                <div>
                    <flux:heading size="lg">Rekening Pembayaran & Pajak</flux:heading>
                    <flux:subheading>Informasi rekening bank transfer manual dan nomor identitas pajak (NPWP).
                    </flux:subheading>
                </div>

                <flux:field>
                    <flux:label>Nomor Pokok Wajib Pajak (NPWP)</flux:label>
                    <flux:input wire:model="npwp" placeholder="Contoh: 01.234.567.8-901.000" />
                    <flux:error name="npwp" />
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <flux:field>
                        <flux:label>Nama Bank</flux:label>
                        <flux:input wire:model="nama_bank" placeholder="Contoh: Bank BCA / Mandiri / BRI" />
                        <flux:error name="nama_bank" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Nomor Rekening</flux:label>
                        <flux:input wire:model="nomor_rekening" placeholder="Contoh: 8830123456" />
                        <flux:error name="nomor_rekening" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Atas Nama (Pemilik Rekening)</flux:label>
                        <flux:input wire:model="atas_nama" placeholder="Contoh: PT GOBILLING NUSANTARA" />
                        <flux:error name="atas_nama" />
                    </flux:field>
                </div>
            </flux:card>

            {{-- 4. Catatan Invoice & Penandatangan --}}
            <flux:card class="p-6 space-y-6">
                <div>
                    <flux:heading size="lg">Kop Invoice & Catatan Tagihan</flux:heading>
                    <flux:subheading>Teks catatan kaki (footer), syarat & ketentuan, serta pejabat penandatangan dokumen
                        tagihan resmi.</flux:subheading>
                </div>

                <flux:field>
                    <flux:label>Catatan / Pesan pada Tagihan</flux:label>
                    <flux:textarea wire:model="catatan_invoice"
                        placeholder="Pesan ucapan terima kasih atau instruksi konfirmasi pembayaran..."
                        rows="3" />
                    <flux:error name="catatan_invoice" />
                </flux:field>

                <flux:field>
                    <flux:label>Syarat & Ketentuan Pembayaran</flux:label>
                    <flux:textarea wire:model="syarat_ketentuan"
                        placeholder="1. Pembayaran tidak dapat dibatalkan... 2. Jatuh tempo..." rows="3" />
                    <flux:error name="syarat_ketentuan" />
                </flux:field>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <flux:field>
                        <flux:label>Nama Pejabat / Penandatangan</flux:label>
                        <flux:input wire:model="nama_penandatangan" placeholder="Contoh: Rian Hidayat, S.Kom" />
                        <flux:error name="nama_penandatangan" />
                    </flux:field>

                    <flux:field>
                        <flux:label>Jabatan Penandatangan</flux:label>
                        <flux:input wire:model="jabatan_penandatangan"
                            placeholder="Contoh: Head of Finance & Billing" />
                        <flux:error name="jabatan_penandatangan" />
                    </flux:field>
                </div>
            </flux:card>

            {{-- Submit Button --}}
            <div class="flex items-center justify-end gap-4 pt-2">
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save"
                    icon="check">
                    <span wire:loading.remove wire:target="save">Simpan Profil Perusahaan</span>
                    <span wire:loading wire:target="save">Menyimpan...</span>
                </flux:button>
            </div>
        </form>
    </x-settings.layout>
</section>
