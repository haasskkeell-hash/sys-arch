<x-layouts::app.sidebar :title="$title ?? null">
    <flux:main class="pb-28 lg:pb-8 {{ auth()->user()->isCustomer() ? 'max-lg:!px-0 max-lg:!pt-0' : '' }}">
        {{ $slot }}
    </flux:main>
</x-layouts::app.sidebar>
