<?php

namespace App\Livewire;

use App\Livewire\Dashboard\AreaAdmin;
use App\Livewire\Dashboard\AreaNoc;
use App\Livewire\Dashboard\AreaTiket;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dashboard: sapaan dan susunan Area Dashboard; tiap area adalah komponen lazy yang tampil sesuai izin.
 */
#[Layout('layouts.app')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(): View
    {
        $user = auth()->user();

        return view('livewire.dashboard', [
            'nama' => $user->name,
            'areaAdmin' => AreaAdmin::bolehLihat($user),
            'areaNoc' => AreaNoc::bolehLihat($user),
            'areaTiket' => AreaTiket::bolehLihat($user),
        ]);
    }
}
