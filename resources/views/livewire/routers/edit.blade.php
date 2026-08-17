<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <flux:heading size="xl">Edit Router</flux:heading>
        <flux:subheading>Perbarui informasi router.</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:card>
            <div class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>Nama Router</flux:label>
                        <flux:input wire:model="name" placeholder="Mis: CORE_MKT_01" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>IP Address</flux:label>
                        <flux:input wire:model="ip_address" placeholder="Mis: 103.X.X.X" />
                        <flux:error name="ip_address" />
                    </flux:field>
                </div>

                @role('super_admin')
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <flux:field>
                            <flux:label>Username</flux:label>
                            <flux:input wire:model="username" />
                            <flux:description>Username untuk akses profil PPP/queue di Mikrotik.</flux:description>
                            <flux:error name="username" />
                        </flux:field>

                        <flux:field>
                            <flux:label>Password Router</flux:label>
                            <flux:input type="password" viewable wire:model="password" />
                            <flux:description>Kosongkan jika tidak ingin mengubah password.</flux:description>
                            <flux:error name="password" />
                        </flux:field>
                    </div>
                @endrole

                <flux:field>
                    <flux:label>Deskripsi</flux:label>
                    <flux:textarea wire:model="description" rows="3"
                        placeholder="Lokasi, fungsi, atau catatan..." />
                    <flux:error name="description" />
                </flux:field>
            </div>
        </flux:card>

        <div class="flex items-center justify-end gap-3">
            <flux:button :href="route('routers.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary">Simpan Perubahan</flux:button>
        </div>
    </form>
</div>
