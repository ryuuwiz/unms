@php
    $perusahaan = \App\Models\Perusahaan::default();
    $brand = \App\Support\BrandPelanggan::untukPortal();
    $noReg = auth('pelanggan')->user()?->pelanggan?->no_reg;

    $nomorWa = \App\Services\Whatsapp\WhatsappClient::normalizePhoneNumber($perusahaan->whatsapp);
    $pesanWa = trim("Halo CS {$brand->nama()}, saya pelanggan dengan No. Registrasi {$noReg}. Saya butuh bantuan terkait layanan internet saya.");
    $telepon = filled($perusahaan->telepon) ? preg_replace('/[^0-9+]/', '', $perusahaan->telepon) : null;
    $email = filled($perusahaan->email) ? $perusahaan->email : null;
@endphp

@if ($nomorWa || $telepon || $email)
    <div id="bantuan" {{ $attributes->class('space-y-3') }}>
        <h2 class="text-sm font-bold text-zinc-900 dark:text-zinc-100 flex items-center gap-2">
            <flux:icon icon="lifebuoy" class="size-4 text-indigo-500" />
            Butuh Bantuan?
        </h2>

        <flux:card class="p-4 flex flex-wrap gap-2">
            @if ($nomorWa)
                <flux:button icon="chat-bubble-left-right" variant="primary" href="https://wa.me/{{ $nomorWa }}?text={{ rawurlencode($pesanWa) }}" target="_blank" rel="noopener" data-kontak="whatsapp">
                    WhatsApp CS
                </flux:button>
            @endif
            @if ($telepon)
                <flux:button icon="phone" href="tel:{{ $telepon }}" data-kontak="telepon">
                    Telepon
                </flux:button>
            @endif
            @if ($email)
                <flux:button icon="envelope" href="mailto:{{ $email }}" data-kontak="email">
                    Email
                </flux:button>
            @endif
        </flux:card>
    </div>
@endif
