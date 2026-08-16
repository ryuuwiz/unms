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
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Edit Pelanggan')]
class Edit extends Component
{
    #[Locked]
    public int $customerId;

    #[Locked]
    public string $customer_code = '';

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $installation_address = '';

    public ?float $lat = null;

    public ?float $lng = null;

    public string $status = 'active';

    public function mount(Customer $customer): void
    {
        $this->authorize('update', $customer);

        $this->customerId = $customer->id;
        $this->customer_code = $customer->customer_code;
        $this->name = $customer->name;
        $this->email = $customer->email ?? '';
        $this->phone = $customer->phone;
        $this->address = $customer->address ?? '';
        $this->installation_address = $customer->installation_address;
        $this->lat = $customer->lat;
        $this->lng = $customer->lng;
        $this->status = $customer->status->value;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(08|\+628|628)[0-9]{8,13}$/'],
            'address' => ['nullable', 'string', 'max:1000'],
            'installation_address' => ['required', 'string', 'max:1000'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap pelanggan wajib diisi.',
            'phone.required' => 'Nomor WhatsApp / HP wajib diisi.',
            'phone.regex' => 'Format nomor HP tidak valid. Gunakan format Indonesia (contoh: 08123456789 atau +628123456789).',
            'email.email' => 'Format alamat email tidak valid.',
            'installation_address.required' => 'Alamat instalasi pemasangan wajib diisi.',
        ];
    }

    public function save(): void
    {
        $customer = Customer::findOrFail($this->customerId);
        $this->authorize('update', $customer);
        $this->validate();

        $oldValues = [
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'address' => $customer->address,
            'installation_address' => $customer->installation_address,
            'lat' => $customer->lat,
            'lng' => $customer->lng,
            'status' => $customer->status->value,
        ];

        $newValues = [
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => Customer::normalizePhone($this->phone),
            'address' => $this->address ?: null,
            'installation_address' => $this->installation_address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => $this->status,
        ];

        $customer->update($newValues);

        /** @var User $actor */
        $actor = Auth::user();
        AuditLogger::recordCustomerUpdated($actor, $customer, $oldValues, $newValues);

        Flux::toast(variant: 'success', text: "Data pelanggan {$customer->name} berhasil diperbarui.");

        $this->redirectRoute('customers.index', navigate: true);
    }

    public function toggleStatus(): void
    {
        $customer = Customer::findOrFail($this->customerId);
        $this->authorize('update', $customer);

        $oldStatus = $customer->status;
        $newStatus = $customer->status === CustomerStatus::Active
            ? CustomerStatus::Inactive
            : CustomerStatus::Active;

        $customer->update(['status' => $newStatus]);
        $this->status = $newStatus->value;

        /** @var User $actor */
        $actor = Auth::user();
        AuditLogger::recordCustomerStatusChanged($actor, $customer, $oldStatus, $newStatus);

        Flux::toast(variant: 'success', text: "Status pelanggan berhasil diubah menjadi {$newStatus->label()}.");
    }

    public function render(): View
    {
        $customer = Customer::with('creator')->findOrFail($this->customerId);

        return view('livewire.customers.edit', [
            'customer' => $customer,
            'statuses' => CustomerStatus::cases(),
        ]);
    }
}
