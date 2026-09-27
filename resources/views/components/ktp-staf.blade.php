@props(['user'])

{{-- Unggah/ganti KTP staf; komponen Livewire induk wajib punya properti $fotoKtp dan method uploadKtp(). --}}
<div class="space-y-3">
    <div>
        <flux:heading size="sm">Foto KTP</flux:heading>
        <flux:subheading size="sm">Disimpan terenkripsi; hanya pemilik akun dan admin yang dapat melihatnya.</flux:subheading>
    </div>

    <div class="flex items-center gap-2">
        @if ($user->getKtpMedia())
            <flux:badge color="green" size="sm">KTP sudah diunggah</flux:badge>
            <flux:button size="sm" variant="ghost" icon="eye" :href="route('users.ktp.preview', $user)" target="_blank">Lihat KTP</flux:button>
        @else
            <flux:badge color="amber" size="sm">KTP belum ada</flux:badge>
        @endif
    </div>

    <form wire:submit="uploadKtp" class="flex items-end gap-2">
        <flux:field>
            <input type="file" wire:model="fotoKtp" accept="image/jpeg,image/png,image/webp" class="text-sm" />
            <flux:error name="fotoKtp" />
        </flux:field>
        <flux:button size="sm" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="fotoKtp,uploadKtp">Unggah KTP</flux:button>
    </form>
</div>
