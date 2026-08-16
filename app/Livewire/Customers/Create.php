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

#[Layout('layouts.app')]
#[Title('Tambah Pelanggan')]
class Create extends Component
{
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public string $installation_address = '';

    public ?float $lat = null;

    public ?float $lng = null;

    public function mount(): void
    {
        $this->authorize('create', Customer::class);
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
            'phone.regex' => 'Format nomor HP tidak valid. Gunakan awalan 08 atau +628 (contoh: 08123456789).',
            'email.email' => 'Format alamat email tidak valid.',
            'installation_address.required' => 'Alamat instalasi pemasangan wajib diisi.',
        ];
    }

    public function save(): void
    {
        $this->authorize('create', Customer::class);
        $this->validate();

        $customer = Customer::create([
            'name' => $this->name,
            'email' => $this->email ?: null,
            'phone' => $this->phone,
            'address' => $this->address ?: null,
            'installation_address' => $this->installation_address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'status' => CustomerStatus::Active,
            'created_by' => Auth::id(),
        ]);

        /** @var User $actor */
        $actor = Auth::user();
        AuditLogger::recordCustomerCreated($actor, $customer);

        Flux::toast(variant: 'success', text: "Pelanggan {$customer->name} ({$customer->customer_code}) berhasil didaftarkan.");

        $this->redirectRoute('customers.index', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.customers.create');
    }
}
