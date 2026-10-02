<span class="relative inline-flex">
    <flux:icon :name="$belumDibaca > 0 ? 'bell-alert' : 'bell'" class="size-5" />
    @if ($belumDibaca > 0)
        <span class="absolute -top-1.5 -right-2 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] leading-none font-bold text-white">{{ $belumDibaca > 99 ? '99+' : $belumDibaca }}</span>
    @endif
</span>
