<?php

namespace App\Livewire\Wilayah\Perumahan;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Tambah Perumahan')]
class Create extends Component
{
    public function render(): View
    {
        return view('livewire.wilayah.perumahan.create');
    }
}
