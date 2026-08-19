@if (app('impersonate')->isImpersonating())
    @php
        $impersonator = app('impersonate')->getImpersonator();
        $currentUser = auth()->user() ?? auth('pelanggan')->user();
        $isPelanggan = auth('pelanggan')->check();
    @endphp
    <div class="sticky top-0 z-50 w-full bg-gradient-to-r from-amber-500 via-orange-500 to-amber-600 px-4 py-2 text-white shadow-md transition-all">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3 text-xs sm:text-sm">
            <div class="flex items-center gap-2.5">
                <span class="flex size-6 items-center justify-center rounded-full bg-white/20 text-white backdrop-blur-xs">
                    <flux:icon icon="user-circle" class="size-4" />
                </span>
                <div>
                    <span class="font-bold tracking-wide uppercase text-[11px] bg-amber-900/30 px-1.5 py-0.5 rounded mr-1.5">
                        Mode Impersonasi
                    </span>
                    <span>Anda sedang login sebagai</span>
                    <span class="font-bold underline decoration-white/50 underline-offset-2">
                        {{ $isPelanggan ? ($currentUser->nama_lengkap ?? $currentUser->email) : $currentUser->name }}
                    </span>
                    @if (! $isPelanggan && $currentUser->roles?->first())
                        <span class="text-white/80 text-xs">({{ $currentUser->roles->first()->name }})</span>
                    @elseif ($isPelanggan && $currentUser->pelanggan)
                        <span class="text-white/80 text-xs font-mono">({{ $currentUser->pelanggan->no_reg }})</span>
                    @endif
                    @if ($impersonator)
                        <span class="hidden text-white/70 sm:inline text-xs">
                            &bull; Oleh: <span class="font-medium text-white">{{ $impersonator->name }}</span>
                        </span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('impersonate.leave') }}"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-1 text-xs font-semibold text-amber-900 shadow-sm transition hover:bg-amber-50 active:scale-95"
                >
                    <flux:icon icon="arrow-right-start-on-rectangle" class="size-3.5 text-amber-800" />
                    <span>Kembali ke Akun Asli</span>
                </a>
            </div>
        </div>
    </div>
@endif
