@php
    $lewat = $tck->isOverdue();
@endphp
<span @class([
    'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold',
    'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300' => $lewat,
    'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' => ! $lewat,
])>
    <flux:icon :name="$lewat ? 'exclamation-triangle' : 'clock'" class="size-3.5" />
    @if ($lewat)<span class="sr-only">SLA terlewat:</span>@else<span class="sr-only">Sisa SLA:</span>@endif
    {{ $tck->sisaWaktuSla() }}
</span>
