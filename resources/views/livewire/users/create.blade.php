<div class="mx-auto max-w-lg space-y-6">
    <div>
        <flux:heading size="xl">Tambah User</flux:heading>
        <flux:subheading>Buat akun staff baru dan tetapkan role-nya.</flux:subheading>
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-5">

        <flux:field>
            <flux:label>Nama Lengkap</flux:label>
            <flux:input wire:model="name" placeholder="Budi Santoso" autofocus />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input wire:model="email" type="email" placeholder="budi@example.com" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>No. Telepon <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:input wire:model="phone" type="tel" placeholder="08xx-xxxx-xxxx" />
            <flux:error name="phone" />
        </flux:field>

        <flux:field>
            <flux:label>Role</flux:label>
            <flux:select wire:model="role" placeholder="Pilih role...">
                <flux:select.option value="">Pilih role...</flux:select.option>
                @foreach ($roles as $role)
                    <flux:select.option value="{{ $role->name }}">
                        {{ Str::title(str_replace('_', ' ', $role->name)) }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="role" />
        </flux:field>

        <flux:separator />

        <flux:field>
            <flux:label>Password</flux:label>
            <flux:input wire:model="password" type="password" placeholder="Min. 8 karakter" />
            <flux:error name="password" />
        </flux:field>

        <flux:field>
            <flux:label>Konfirmasi Password</flux:label>
            <flux:input wire:model="password_confirmation" type="password" placeholder="Ulangi password" />
        </flux:field>

        <div class="flex items-center gap-3 pt-2">
            <flux:spacer />
            <flux:button :href="route('users.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary">Simpan User</flux:button>
        </div>

    </form>
</div>
