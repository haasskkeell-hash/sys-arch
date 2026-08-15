@props([
    'actions' => null,
    'label' => __('Actions'),
])

<div {{ $attributes->merge(['class' => 'shrink-0']) }} data-app-table-actions-menu>
    <flux:dropdown align="end">
        <flux:tooltip :content="$label" position="bottom">
            <flux:button variant="ghost" square size="sm" icon="ellipsis-vertical" aria-label="{{ $label }}" />
        </flux:tooltip>

        <flux:menu>
            @if ($actions !== null)
                @foreach ($actions as $action)
                    @if (($action['type'] ?? null) === 'separator')
                        <flux:menu.separator />
                    @elseif (! empty($action['href']))
                        <flux:menu.item
                            :href="$action['href']"
                            :icon="$action['icon']"
                            wire:navigate
                        >
                            {{ $action['label'] }}
                        </flux:menu.item>
                    @else
                        <flux:menu.item
                            as="button"
                            type="button"
                            :icon="$action['icon']"
                            wire:click="{{ $action['wire'] }}"
                        >
                            {{ $action['label'] }}
                        </flux:menu.item>
                    @endif
                @endforeach
            @else
                {{ $slot }}
            @endif
        </flux:menu>
    </flux:dropdown>
</div>
