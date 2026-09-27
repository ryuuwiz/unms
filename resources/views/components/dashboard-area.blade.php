@props(['judul', 'subjudul'])

{{-- Bingkai satu Area Dashboard: judul area dan isinya (komponen area lazy). --}}
<section {{ $attributes->class('rounded-3xl border border-zinc-200/70 bg-zinc-50/60 p-4 sm:p-6 space-y-5 dark:border-zinc-700/60 dark:bg-zinc-900/30') }}>
    <div class="border-b border-zinc-200/70 pb-4 dark:border-zinc-700/60">
        <flux:heading size="lg">{{ $judul }}</flux:heading>
        <flux:subheading>{{ $subjudul }}</flux:subheading>
    </div>

    {{ $slot }}
</section>
