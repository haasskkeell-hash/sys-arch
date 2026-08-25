<div
    class="settings-page-layout mx-auto flex w-full max-w-6xl items-start bg-white text-zinc-900 dark:bg-zinc-950 dark:text-zinc-100 max-md:flex-col max-md:px-5"
    data-settings-page
>
    <div class="settings-page-navigation me-10 w-full pb-4 md:w-[220px] md:pb-0">
        <flux:navlist aria-label="{{ __('Settings') }}" class="gap-1">
            <flux:navlist.item
                :href="route('profile.edit')"
                icon="user-circle"
                wire:navigate
                class="max-md:min-h-14 max-md:rounded-xl max-md:px-4"
            >
                {{ __('Profile') }}
            </flux:navlist.item>
            <flux:navlist.item
                :href="route('security.edit')"
                icon="shield-check"
                wire:navigate
                class="max-md:min-h-14 max-md:rounded-xl max-md:px-4"
            >
                {{ __('Security') }}
            </flux:navlist.item>
            <flux:navlist.item
                :href="route('appearance.edit')"
                icon="swatch"
                wire:navigate
                class="max-md:min-h-14 max-md:rounded-xl max-md:px-4"
            >
                {{ __('Appearance') }}
            </flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="settings-page-content flex-1 self-stretch max-md:pt-6 max-md:pb-8">
        <flux:heading size="lg" class="tracking-tight">{{ $heading ?? '' }}</flux:heading>
        <flux:subheading class="mt-1">{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
