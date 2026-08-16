<?php

namespace App\Livewire\Customers;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogger;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Data Pelanggan')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public string $filterStatus = '';

    public string $filterScope = 'all'; // 'all' or 'my'

    public ?int $deletingCustomerId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingFilterScope(): void
    {
        $this->resetPage();
    }

    public function confirmDelete(int $id): void
    {
        $this->deletingCustomerId = $id;
    }

    public function deleteCustomer(): void
    {
        if (! $this->deletingCustomerId) {
            return;
        }

        $customer = Customer::findOrFail($this->deletingCustomerId);

        $this->authorize('delete', $customer);

        /** @var User $actor */
        $actor = Auth::user();
        AuditLogger::recordCustomerDeleted($actor, $customer);

        $customer->delete();

        $this->deletingCustomerId = null;
        Flux::toast(variant: 'success', text: 'Data pelanggan berhasil dihapus.');
    }

    public function toggleStatus(int $id): void
    {
        $customer = Customer::findOrFail($id);

        $this->authorize('update', $customer);

        $oldStatus = $customer->status;
        $newStatus = $customer->status === CustomerStatus::Active
            ? CustomerStatus::Inactive
            : CustomerStatus::Active;

        $customer->update(['status' => $newStatus]);

        /** @var User $actor */
        $actor = Auth::user();
        AuditLogger::recordCustomerStatusChanged($actor, $customer, $oldStatus, $newStatus);

        Flux::toast(variant: 'success', text: "Status pelanggan diubah menjadi {$newStatus->label()}.");
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $isSales = $user->hasRole('sales');

        $customers = Customer::query()
            ->when($this->search, fn ($q) => $q->search($this->search))
            ->when($this->filterStatus, fn ($q) => $q->where('status', $this->filterStatus))
            ->when($this->filterScope === 'my', fn ($q) => $q->where('created_by', $user->id))
            ->with('creator')
            ->latest()
            ->paginate(15);

        return view('livewire.customers.index', [
            'customers' => $customers,
            'statuses' => CustomerStatus::cases(),
            'isSales' => $isSales,
            'user' => $user,
        ]);
    }
}
