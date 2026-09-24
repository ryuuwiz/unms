<div class="mx-auto max-w-2xl space-y-6">
    <div>
        <flux:heading size="xl">Tambah IP Publik</flux:heading>
        <flux:subheading>Daftarkan alamat IPv4 publik dedicated ke inventaris router.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-6">
        @include('livewire.ip-publik._form', ['routers' => $routers, 'terpakai' => false])

        <div class="flex items-center justify-end gap-3 pt-2">
            <flux:button :href="route('ip-publik.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary" icon="check">Simpan IP Publik</flux:button>
        </div>
    </form>
</div>
