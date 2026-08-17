<?php

namespace App\Livewire\Packages;

use App\Enums\PackageStatus;
use App\Models\Package;
use App\Models\User;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Paket Internet')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public bool $isModalOpen = false;

    public ?int $packageId = null;

    public string $name = '';

    public ?int $download_speed_mbps = null;

    public ?int $upload_speed_mbps = null;

    public ?int $price = null;

    public ?string $description = null;

    public string $status = 'active';

    public bool $uploadManuallyChanged = false;

    public ?int $deletingPackageId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatedDownloadSpeedMbps(?int $value): void
    {
        if (! $this->uploadManuallyChanged && ! $this->packageId && $value !== null) {
            $this->upload_speed_mbps = $value;
        }
    }

    public function updatedUploadSpeedMbps(?int $value): void
    {
        $this->uploadManuallyChanged = true;
    }

    public function openCreateModal(): void
    {
        $this->authorize('create', Package::class);

        $this->resetValidation();
        $this->packageId = null;
        $this->name = '';
        $this->download_speed_mbps = null;
        $this->upload_speed_mbps = null;
        $this->price = null;
        $this->description = null;
        $this->status = PackageStatus::Active->value;
        $this->uploadManuallyChanged = false;
        $this->isModalOpen = true;
    }

    public function openEditModal(int $id): void
    {
        $package = Package::findOrFail($id);

        $this->authorize('update', $package);

        $this->resetValidation();
        $this->packageId = $package->id;
        $this->name = $package->name;
        $this->download_speed_mbps = $package->download_speed_mbps;
        $this->upload_speed_mbps = $package->upload_speed_mbps;
        $this->price = $package->price;
        $this->description = $package->description;
        $this->status = $package->status->value;
        $this->uploadManuallyChanged = true;
        $this->isModalOpen = true;
    }

    public function save(): void
    {
        $rules = [
            'name' => ['required', 'string', 'max:255', Rule::unique('packages', 'name')->ignore($this->packageId)],
            'download_speed_mbps' => ['required', 'integer', 'min:1', 'max:100000'],
            'upload_speed_mbps' => ['required', 'integer', 'min:1', 'max:100000'],
            'price' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::enum(PackageStatus::class)],
        ];

        $validated = $this->validate($rules, [
            'name.required' => 'Nama paket wajib diisi.',
            'name.unique' => 'Nama paket sudah terdaftar.',
            'download_speed_mbps.required' => 'Kecepatan unduh (download) wajib diisi.',
            'download_speed_mbps.min' => 'Kecepatan unduh minimal 1 Mbps.',
            'upload_speed_mbps.required' => 'Kecepatan unggah (upload) wajib diisi.',
            'upload_speed_mbps.min' => 'Kecepatan unggah minimal 1 Mbps.',
            'price.required' => 'Tarif bulanan wajib diisi.',
            'price.min' => 'Tarif bulanan harus lebih besar dari 0 Rupiah.',
        ]);

        /** @var User $actor */
        $actor = Auth::user();

        if ($this->packageId) {
            $package = Package::findOrFail($this->packageId);
            $this->authorize('update', $package);

            $oldValues = [
                'name' => $package->name,
                'download_speed_mbps' => $package->download_speed_mbps,
                'upload_speed_mbps' => $package->upload_speed_mbps,
                'price' => $package->price,
                'description' => $package->description,
                'status' => $package->status->value,
            ];

            $package->update($validated);

            $newValues = [
                'name' => $package->name,
                'download_speed_mbps' => $package->download_speed_mbps,
                'upload_speed_mbps' => $package->upload_speed_mbps,
                'price' => $package->price,
                'description' => $package->description,
                'status' => $package->status->value,
            ];

            AuditLogger::recordPackageUpdated($actor, $package, $oldValues, $newValues);

            Flux::toast(variant: 'success', text: 'Data paket internet berhasil diperbarui.');
        } else {
            $this->authorize('create', Package::class);

            $package = Package::create($validated);

            AuditLogger::recordPackageCreated($actor, $package);

            Flux::toast(variant: 'success', text: 'Paket internet baru berhasil ditambahkan.');
        }

        $this->isModalOpen = false;
    }

    public function toggleStatus(int $id): void
    {
        $package = Package::findOrFail($id);

        $this->authorize('update', $package);

        $oldStatus = $package->status;
        $newStatus = $oldStatus === PackageStatus::Active
            ? PackageStatus::Inactive
            : PackageStatus::Active;

        $package->update(['status' => $newStatus]);

        /** @var User $actor */
        $actor = Auth::user();
        AuditLogger::recordPackageStatusChanged($actor, $package, $oldStatus, $newStatus);

        Flux::toast(variant: 'success', text: "Status paket diubah menjadi {$newStatus->label()}.");
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingPackageId = $id;
    }

    public function deletePackage(): void
    {
        if (! $this->deletingPackageId) {
            return;
        }

        $package = Package::findOrFail($this->deletingPackageId);

        $this->authorize('delete', $package);

        /** @var User $actor */
        $actor = Auth::user();
        AuditLogger::recordPackageDeleted($actor, $package);

        $package->delete();

        $this->deletingPackageId = null;
        Flux::toast(variant: 'success', text: 'Paket internet berhasil dihapus.');
    }

    public function render(): View
    {
        $packages = Package::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->latest()
            ->paginate(10);

        return view('livewire.packages.index', [
            'packages' => $packages,
            'statuses' => PackageStatus::cases(),
        ]);
    }
}
