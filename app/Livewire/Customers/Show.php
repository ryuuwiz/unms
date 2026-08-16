<?php

namespace App\Livewire\Customers;

use App\Models\AuditLog;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Detail Pelanggan')]
class Show extends Component
{
    public int $customerId;

    public string $activeTab = 'overview';

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);
        $this->customerId = $customer->id;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function render(): View
    {
        $customer = Customer::with('creator')->findOrFail($this->customerId);

        /** @var Collection<int, AuditLog> $auditLogs */
        $auditLogs = AuditLog::query()
            ->where('subject_type', Customer::class)
            ->where('subject_id', $customer->id)
            ->with('user')
            ->latest()
            ->get();

        return view('livewire.customers.show', [
            'customer' => $customer,
            'auditLogs' => $auditLogs,
        ]);
    }
}
