<div class="p-6">
    <div class="flex justify-between items-center mb-6">
        <flux:heading size="xl">Users</flux:heading>
        <flux:button wire:click="$set('showModal', true)" variant="primary" icon="plus">New User</flux:button>
    </div>

    <div class="mb-4">
        <flux:input wire:model.live="search" icon="magnifying-glass" placeholder="Search users..." />
    </div>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>Name</flux:table.column>
            <flux:table.column>Email</flux:table.column>
            <flux:table.column>Joined</flux:table.column>
            <flux:table.column>Actions</flux:table.column>
        </flux:table.columns>

        <flux:table.rows>
            @forelse ($users as $user)
                <flux:table.row :key="$user->id">
                    <flux:table.cell class="font-medium">{{ $user->name }}</flux:table.cell>
                    <flux:table.cell>{{ $user->email }}</flux:table.cell>
                    <flux:table.cell>{{ $user->created_at->format('M d, Y') }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button wire:click="confirmDelete({{ $user->id }})" variant="danger" size="sm" icon="trash">Delete</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="4" class="text-center text-zinc-500">No users found.</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="mt-4">
        {{ $users->links() }}
    </div>

    <!-- Create User Modal -->
    <flux:modal wire:model="showModal" class="md:w-96">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Create User</flux:heading>
                <flux:subheading>Add a new user to the system.</flux:subheading>
            </div>

            <flux:input wire:model="name" label="Name" placeholder="John Doe" />
            <flux:input wire:model="email" label="Email" type="email" placeholder="john@example.com" />

            <div class="flex space-x-2">
                <flux:spacer />
                <flux:button wire:click="$set('showModal', false)" variant="ghost">Cancel</flux:button>
                <flux:button wire:click="save" variant="primary">Save changes</flux:button>
            </div>
        </div>
    </flux:modal>

    <!-- Delete Confirmation Modal -->
    <flux:modal name="delete-user-modal" class="min-w-[22rem]">
        <div class="space-y-6">
            <div>
                <flux:heading size="lg">Delete User?</flux:heading>
                <flux:subheading>
                    Are you sure you want to delete this user? This action cannot be undone.
                </flux:subheading>
            </div>

            <div class="flex gap-2">
                <flux:spacer />
                <flux:modal.close>
                    <flux:button variant="ghost">Cancel</flux:button>
                </flux:modal.close>
                <flux:button wire:click="deleteUser" variant="danger">Delete</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
