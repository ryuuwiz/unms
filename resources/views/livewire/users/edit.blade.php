<div class="mx-auto max-w-lg space-y-6">
    <div class="flex items-start justify-between">
        <div>
            <flux:heading size="xl">Edit User</flux:heading>
            <flux:subheading>Ubah informasi dan role staff.</flux:subheading>
        </div>
        {{-- Status toggle --}}
        @if ($user->isActive())
            <flux:button
                wire:click="toggleStatus"
                wire:confirm="Yakin ingin menonaktifkan akun ini?"
                variant="danger"
                size="sm"
                icon="lock-closed"
            >
                Nonaktifkan
            </flux:button>
        @else
            <flux:button
                wire:click="toggleStatus"
                variant="primary"
                size="sm"
                icon="lock-open"
            >
                Aktifkan
            </flux:button>
        @endif
    </div>

    <flux:separator />

    <form wire:submit="save" class="space-y-5">

        <flux:field>
            <flux:label>Nama Lengkap</flux:label>
            <flux:input wire:model="name" placeholder="Nama lengkap" />
            <flux:error name="name" />
        </flux:field>

        <flux:field>
            <flux:label>Email</flux:label>
            <flux:input wire:model="email" type="email" />
            <flux:error name="email" />
        </flux:field>

        <flux:field>
            <flux:label>No. Telepon <span class="text-zinc-400 font-normal">(opsional)</span></flux:label>
            <flux:input wire:model="phone" type="tel" placeholder="08xx-xxxx-xxxx" />
            <flux:error name="phone" />
        </flux:field>

        <flux:field>
            <flux:label>Role</flux:label>
            <flux:select wire:model="role">
                @foreach ($roles as $availableRole)
                    <flux:select.option value="{{ $availableRole->name }}">
                        {{ Str::title(str_replace('_', ' ', $availableRole->name)) }}
                    </flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="role" />
        </flux:field>

        <div class="flex items-center gap-3 pt-2">
            <flux:badge :color="$user->isActive() ? 'green' : 'zinc'" size="sm">
                {{ $user->status->label() }}
            </flux:badge>
            <flux:spacer />
            <flux:button :href="route('users.index')" wire:navigate variant="ghost">Batal</flux:button>
            <flux:button type="submit" variant="primary">Simpan Perubahan</flux:button>
        </div>

    </form>

    <flux:separator />

    {{-- Permissions Section --}}
    <div class="space-y-3">
        <div>
            <flux:heading size="sm">Permissions</flux:heading>
            <flux:subheading size="sm">Permissions yang dimiliki user ini via role-nya (read-only).</flux:subheading>
        </div>

        @if (empty($groupedPermissions))
            <flux:text class="text-zinc-400 text-sm">User ini belum memiliki permissions.</flux:text>
        @else
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($groupedPermissions as $module => $permissions)
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                        <flux:text class="mb-2 font-semibold capitalize text-xs text-zinc-500 uppercase tracking-wide">
                            {{ Str::title($module) }}
                        </flux:text>
                        <div class="space-y-1">
                            @foreach ($permissions as $perm)
                                <div class="flex items-center gap-2">
                                    <flux:icon.check-circle class="size-3.5 text-green-500 shrink-0" />
                                    <flux:text size="sm">{{ Str::title(str_replace('_', ' ', $perm)) }}</flux:text>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <flux:separator />

    {{-- Reset Password Section --}}
    <div class="space-y-3">
        <div>
            <flux:heading size="sm">Reset Password</flux:heading>
            <flux:subheading size="sm">Generate password baru untuk user ini. Password lama akan langsung tidak berlaku.</flux:subheading>
        </div>

        @if ($generatedPassword)
            <flux:callout variant="success" icon="key">
                <flux:callout.heading>Password baru berhasil digenerate</flux:callout.heading>
                <flux:callout.text>
                    Salin password di bawah dan berikan ke user. Password ini hanya ditampilkan sekali.
                </flux:callout.text>
                <div class="mt-3 flex items-center gap-3 rounded-md bg-white/60 px-4 py-2 font-mono text-sm dark:bg-zinc-800/60">
                    <span class="flex-1 select-all tracking-widest">{{ $generatedPassword }}</span>
                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon="clipboard"
                        x-on:click="navigator.clipboard.writeText('{{ $generatedPassword }}'); $el.innerText = 'Disalin!'"
                    >
                        Salin
                    </flux:button>
                </div>
            </flux:callout>
        @endif

        <div>
            <flux:button
                wire:click="resetPassword"
                wire:confirm="Yakin ingin reset password user ini? Password lama akan langsung tidak berlaku."
                variant="ghost"
                icon="arrow-path"
                size="sm"
            >
                Generate Password Baru
            </flux:button>
        </div>
    </div>
</div>
