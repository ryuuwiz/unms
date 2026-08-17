<?php

namespace App\Livewire\Wilayah\Kecamatan;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Kecamatan')]
class Create extends Component
{
    public function render(): View
    {
        return view('livewire.wilayah.kecamatan.create');
    }
}
