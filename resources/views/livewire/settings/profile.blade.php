<section class="w-full">
    @include('partials.settings-heading')

    <flux:heading class="sr-only">{{ __('Profile settings') }}</flux:heading>

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <div class="flex items-center gap-4 mb-6">
            <flux:avatar size="xl" :src="auth()->user()->fotoProfilUrl()" :initials="auth()->user()->initials()" />
            <form wire:submit="uploadFotoProfil" class="flex items-end gap-2">
                <flux:field>
                    <flux:label>{{ __('Foto Profil') }}</flux:label>
                    <input type="file" wire:model="fotoProfil" accept="image/*" class="text-sm" />
                    <flux:error name="fotoProfil" />
                </flux:field>
                <flux:button size="sm" variant="primary" type="submit" wire:loading.attr="disabled" wire:target="fotoProfil,uploadFotoProfil">{{ __('Unggah') }}</flux:button>
            </form>
        </div>

        <form wire:submit="updateProfileInformation" class="my-6 w-full space-y-6">
            <flux:input wire:model="name" :label="__('Name')" type="text" required autofocus autocomplete="name" />

            <div>
                <flux:input wire:model="email" :label="__('Email')" type="email" required autocomplete="email" />

            </div>

            <div>
                <flux:input wire:model="phone" :label="__('No. Telepon')" type="tel" autocomplete="tel" placeholder="08xx-xxxx-xxxx" />
            </div>

            <div class="flex items-center gap-4">
                <flux:button variant="primary" type="submit">{{ __('Save') }}</flux:button>
            </div>
        </form>

            <livewire:settings.delete-user-form />
    </x-settings.layout>
</section>
