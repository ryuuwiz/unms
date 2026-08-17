<?php

namespace App\Livewire\Wilayah\Perumahan;

use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Perumahan')]
class Edit extends Component
{
    public int $id;

    public function mount(int $id): void
    {
        $this->id = $id;
    }

    public function render(): View
    {
        return view('livewire.wilayah.perumahan.edit');
    }
}
