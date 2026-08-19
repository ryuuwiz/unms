<?php

namespace App\Livewire\Portal\Auth;

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Ganti Password Portal')]
class GantiPassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (! Auth::guard('pelanggan')->check()) {
            $this->redirectRoute('portal.login', navigate: true);
        }
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password:pelanggan'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = Auth::guard('pelanggan')->user();
        $user->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        Flux::toast(variant: 'success', text: 'Password akun portal Anda berhasil diperbarui!');
    }

    public function render(): View
    {
        return view('livewire.portal.auth.ganti-password');
    }
}
