<?php

namespace App\Livewire\Portal\Auth;

use App\Models\AkunPelanggan;
use App\Models\Pelanggan;
use Flux\Flux;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.portal')]
#[Title('Aktivasi & Klaim Akun Portal')]
class KlaimAkun extends Component
{
    public string $no_reg = '';

    public string $no_hp = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $verified = false;

    public ?Pelanggan $pelanggan = null;

    public function verifikasiIdentitas(): void
    {
        $this->validate([
            'no_reg' => ['required', 'string'],
            'no_hp' => ['required', 'string'],
        ]);

        $normalizedPhone = Pelanggan::normalizePhone($this->no_hp);

        $pelanggan = Pelanggan::where('no_reg', trim($this->no_reg))
            ->where(function ($q) use ($normalizedPhone) {
                $q->where('no_hp', $normalizedPhone)
                    ->orWhere('no_hp', 'like', '%'.substr($normalizedPhone, -8));
            })
            ->first();

        if (! $pelanggan) {
            $this->addError('no_reg', 'Kombinasi Nomor Registrasi dan Nomor HP tidak ditemukan.');

            return;
        }

        $this->pelanggan = $pelanggan;
        $this->email = $pelanggan->email ?? '';
        $this->verified = true;
    }

    public function simpanAkun(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (! $this->pelanggan) {
            $this->verified = false;

            return;
        }

        // Update email di data pelanggan jika berubah/baru diisi
        if ($this->pelanggan->email !== $this->email) {
            $this->pelanggan->update(['email' => strtolower(trim($this->email))]);
        }

        // Buat atau update AkunPelanggan
        $akun = AkunPelanggan::updateOrCreate(
            ['pelanggan_id' => $this->pelanggan->id],
            [
                'email' => strtolower(trim($this->email)),
                'password' => Hash::make($this->password),
            ]
        );

        Flux::toast(variant: 'success', text: 'Akun Portal berhasil diaktifkan! Silakan login dengan password baru Anda.');

        $this->redirectRoute('portal.login', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.portal.auth.klaim-akun');
    }
}
