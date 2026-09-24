<?php

namespace App\Livewire\Settings;

use App\Models\TemplateDeskripsiTagihan as TemplateModel;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Template Deskripsi Tagihan Gateway')]
class TemplateDeskripsiTagihan extends Component
{
    public bool $showModal = false;

    public ?int $editingId = null;

    public string $nama = '';

    public string $konten = '';

    public bool $is_default = false;

    public function openCreateModal(): void
    {
        $this->authorize('payment_gateway.buat');

        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal(int $id): void
    {
        $this->authorize('payment_gateway.ubah');

        $template = TemplateModel::findOrFail($id);

        $this->editingId = $template->id;
        $this->nama = $template->nama;
        $this->konten = $template->konten;
        $this->is_default = $template->is_default;
        $this->showModal = true;
    }

    public function simpan(): void
    {
        $this->authorize($this->editingId ? 'payment_gateway.ubah' : 'payment_gateway.buat');

        $this->validate([
            'nama' => ['required', 'string', 'max:100'],
            'konten' => [
                'required', 'string', 'max:500',
                function (string $attribute, mixed $value, \Closure $gagal): void {
                    $takDikenal = TemplateModel::placeholderTakDikenal((string) $value);

                    if ($takDikenal !== []) {
                        $gagal('Placeholder tidak dikenal: {'.implode('}, {', $takDikenal).'}.');
                    }
                },
            ],
            'is_default' => ['boolean'],
        ]);

        DB::transaction(function () {
            $template = $this->editingId ? TemplateModel::findOrFail($this->editingId) : new TemplateModel;

            // Selalu ada tepat satu default: yang pertama otomatis default, dan default tidak bisa dicopot lewat edit.
            $jadiDefault = $this->is_default || $template->is_default || ! TemplateModel::query()->exists();

            if ($jadiDefault) {
                TemplateModel::query()->whereKeyNot($template->getKey() ?? 0)->update(['is_default' => false]);
            }

            $template->fill(['nama' => trim($this->nama), 'konten' => trim($this->konten), 'is_default' => $jadiDefault])->save();
        });

        Flux::toast(variant: 'success', text: 'Template berhasil disimpan.');
        $this->showModal = false;
        $this->resetForm();
    }

    public function jadikanDefault(int $id): void
    {
        $this->authorize('payment_gateway.ubah');

        DB::transaction(function () use ($id) {
            TemplateModel::query()->update(['is_default' => false]);
            TemplateModel::findOrFail($id)->update(['is_default' => true]);
        });

        Flux::toast(variant: 'success', text: 'Template default diperbarui.');
    }

    public function hapus(int $id): void
    {
        $this->authorize('payment_gateway.hapus');

        $template = TemplateModel::findOrFail($id);

        if ($template->is_default) {
            Flux::toast(variant: 'danger', text: 'Template default tidak dapat dihapus; jadikan template lain default terlebih dahulu.');

            return;
        }

        $template->delete();
        Flux::toast(variant: 'success', text: 'Template dihapus.');
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->nama = '';
        $this->konten = '';
        $this->is_default = false;
        $this->resetValidation();
    }

    public function render(): View
    {
        return view('livewire.settings.template-deskripsi-tagihan', [
            'templates' => TemplateModel::query()->orderByDesc('is_default')->orderBy('nama')->get(),
            'placeholders' => TemplateModel::PLACEHOLDERS,
        ]);
    }
}
