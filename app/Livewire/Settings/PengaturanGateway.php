<?php

namespace App\Livewire\Settings;

use App\Models\PengaturanGateway as PengaturanGatewayModel;
use Flux\Flux;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Pengaturan Payment Gateway')]
class PengaturanGateway extends Component
{
    public float $fee_va_nominal = 0.0;

    public float $fee_qris_persen = 0.0;

    public float $fee_qris_nominal = 0.0;

    public bool $bebankan_ke_pelanggan = false;

    public bool $is_active = true;

    public bool $sandbox_mode = true;

    public function mount(): void
    {
        $setting = PengaturanGatewayModel::getXenditSetting();

        $this->fee_va_nominal = (float) $setting->fee_va_nominal;
        $this->fee_qris_persen = (float) $setting->fee_qris_persen;
        $this->fee_qris_nominal = (float) $setting->fee_qris_nominal;
        $this->bebankan_ke_pelanggan = (bool) $setting->bebankan_ke_pelanggan;
        $this->is_active = (bool) $setting->is_active;
        $this->sandbox_mode = (bool) $setting->sandbox_mode;
    }

    public function save(): void
    {
        $this->validate([
            'fee_va_nominal' => ['required', 'numeric', 'min:0'],
            'fee_qris_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'fee_qris_nominal' => ['required', 'numeric', 'min:0'],
            'bebankan_ke_pelanggan' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'sandbox_mode' => ['required', 'boolean'],
        ]);

        $setting = PengaturanGatewayModel::getXenditSetting();
        $setting->update([
            'fee_va_nominal' => $this->fee_va_nominal,
            'fee_qris_persen' => $this->fee_qris_persen,
            'fee_qris_nominal' => $this->fee_qris_nominal,
            'bebankan_ke_pelanggan' => $this->bebankan_ke_pelanggan,
            'is_active' => $this->is_active,
            'sandbox_mode' => $this->sandbox_mode,
        ]);

        Flux::toast(variant: 'success', text: 'Pengaturan payment gateway berhasil disimpan!');
    }

    public function render(): View
    {
        return view('livewire.settings.pengaturan-gateway');
    }
}
