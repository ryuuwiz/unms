@props(['title' => null])

<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main>
        {{ $slot }}

        <footer class="mt-12 border-t border-zinc-200 pt-6 text-center text-xs text-zinc-500 dark:border-zinc-700 dark:text-zinc-400">
            {{ config('app.name') }} &copy; {{ date('Y') }} {{ \App\Models\Perusahaan::default()->nama_brand }} &middot; Made by MyArsyila
        </footer>
    </flux:main>
</x-layouts::app.sidebar>
