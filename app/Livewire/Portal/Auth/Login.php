<?php

namespace App\Livewire\Portal\Auth;

use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Login Portal Pelanggan')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if (Auth::guard('pelanggan')->check()) {
            $this->redirectRoute('portal.dashboard', navigate: true);
        }
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('pelanggan')->attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            throw ValidationException::withMessages([
                'email' => __('Email atau password yang Anda masukkan tidak sesuai.'),
            ]);
        }

        session()->regenerate();

        Flux::toast(variant: 'success', text: 'Selamat datang kembali di Portal Pelanggan!');

        $this->redirectIntended(route('portal.dashboard'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.portal.auth.login');
    }
}
