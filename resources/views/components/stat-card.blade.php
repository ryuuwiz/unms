@props([
    'title',
    'value',
    'subvalue' => null,
    'icon' => null,
    'trend' => null,
    'trendType' => 'up', // 'up', 'down', 'neutral'
    'trendLabel' => null,
    'color' => 'indigo', // 'indigo', 'emerald', 'amber', 'rose', 'cyan', 'purple'
    'href' => null,
])

@php
    $colorClasses = match ($color) {
        'emerald' => [
            'icon_bg' => 'bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 border-emerald-200/80 dark:border-emerald-800/50 shadow-sm shadow-emerald-500/10',
            'glow' => 'group-hover:border-emerald-300 dark:group-hover:border-emerald-600/70 group-hover:shadow-emerald-500/5',
        ],
        'amber' => [
            'icon_bg' => 'bg-amber-50 dark:bg-amber-950/70 text-amber-600 dark:text-amber-400 border-amber-200/80 dark:border-amber-800/50 shadow-sm shadow-amber-500/10',
            'glow' => 'group-hover:border-amber-300 dark:group-hover:border-amber-600/70 group-hover:shadow-amber-500/5',
        ],
        'rose' => [
            'icon_bg' => 'bg-rose-50 dark:bg-rose-950/70 text-rose-600 dark:text-rose-400 border-rose-200/80 dark:border-rose-800/50 shadow-sm shadow-rose-500/10',
            'glow' => 'group-hover:border-rose-300 dark:group-hover:border-rose-600/70 group-hover:shadow-rose-500/5',
        ],
        'cyan' => [
            'icon_bg' => 'bg-cyan-50 dark:bg-cyan-950/70 text-cyan-600 dark:text-cyan-400 border-cyan-200/80 dark:border-cyan-800/50 shadow-sm shadow-cyan-500/10',
            'glow' => 'group-hover:border-cyan-300 dark:group-hover:border-cyan-600/70 group-hover:shadow-cyan-500/5',
        ],
        'purple' => [
            'icon_bg' => 'bg-purple-50 dark:bg-purple-950/70 text-purple-600 dark:text-purple-400 border-purple-200/80 dark:border-purple-800/50 shadow-sm shadow-purple-500/10',
            'glow' => 'group-hover:border-purple-300 dark:group-hover:border-purple-600/70 group-hover:shadow-purple-500/5',
        ],
        default => [
            'icon_bg' => 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-400 border-indigo-200/80 dark:border-indigo-800/50 shadow-sm shadow-indigo-500/10',
            'glow' => 'group-hover:border-indigo-300 dark:group-hover:border-indigo-600/70 group-hover:shadow-indigo-500/5',
        ],
    };

    $trendBadgeClasses = match ($trendType) {
        'up' => 'bg-emerald-50 dark:bg-emerald-950/90 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800/60',
        'down' => 'bg-rose-50 dark:bg-rose-950/90 text-rose-700 dark:text-rose-400 border-rose-200 dark:border-rose-800/60',
        default => 'bg-zinc-100 dark:bg-zinc-800 text-zinc-700 dark:text-zinc-300 border-zinc-200 dark:border-zinc-700',
    };

    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" wire:navigate @endif
    {{ $attributes->merge(['class' => 'group relative block p-6 lg:p-7 rounded-2xl bg-white dark:bg-zinc-800 border border-zinc-200/80 dark:border-zinc-700/80 shadow-xs hover:shadow-lg transition-all duration-300 ' . ($href ? 'cursor-pointer ' : '') . $colorClasses['glow']]) }}
>
    <div class="flex items-center justify-between gap-5">
        <div class="space-y-2 flex-1 min-w-0">
            <div class="text-sm font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider truncate">
                {{ $title }}
            </div>
            <div class="text-3xl lg:text-4xl font-extrabold text-zinc-900 dark:text-white tracking-tight truncate">
                {{ $value }}
            </div>
        </div>

        @if($icon)
            <div class="size-14 rounded-2xl flex items-center justify-center shrink-0 border transition-transform duration-300 group-hover:scale-105 {{ $colorClasses['icon_bg'] }}">
                <flux:icon name="{{ $icon }}" class="size-7" />
            </div>
        @endif
    </div>

    @if($trend !== null || $subvalue || $trendLabel)
        <div class="mt-5 pt-4 border-t border-zinc-100 dark:border-zinc-700/60 flex items-center gap-2.5 text-xs lg:text-sm flex-wrap">
            @if($trend !== null)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg border font-bold text-xs {{ $trendBadgeClasses }}">
                    @if($trendType === 'up')
                        <flux:icon name="arrow-trending-up" class="size-3.5" />
                    @elseif($trendType === 'down')
                        <flux:icon name="arrow-trending-down" class="size-3.5" />
                    @endif
                    {{ $trend }}
                </span>
            @endif

            @if($trendLabel)
                <span class="text-zinc-500 dark:text-zinc-400 font-medium truncate">{{ $trendLabel }}</span>
            @endif

            @if($subvalue)
                <span class="ml-auto text-zinc-400 dark:text-zinc-500 font-medium truncate">{{ $subvalue }}</span>
            @endif
        </div>
    @endif
</{{ $tag }}>
