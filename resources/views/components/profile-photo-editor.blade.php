@props([
    'user',
    'pendingPhoto' => null,
    'inputId' => 'profile-photo-input',
])

@php($photoUrl = $pendingPhoto?->temporaryUrl() ?: $user->avatarUrl())

<div class="flex flex-wrap items-center gap-3" data-profile-photo-editor data-profile-photo-inline>
    <label for="{{ $inputId }}" data-profile-photo-trigger class="group flex min-w-0 flex-1 cursor-pointer items-center gap-3 rounded-2xl py-1 text-left outline-none transition hover:bg-zinc-100/70 focus-within:ring-2 focus-within:ring-violet-500/40 dark:hover:bg-white/5">
        <span class="relative flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-violet-100 text-xl font-semibold text-violet-700 dark:bg-violet-500/15 dark:text-violet-300">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="" class="size-full object-cover" data-profile-photo-preview>
            @else
                <span data-profile-photo-fallback>{{ $user->initials() }}</span>
            @endif
            <span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-zinc-950/45 text-white opacity-0 transition group-hover:opacity-100 group-focus-within:opacity-100">
                <flux:icon name="camera" class="size-4" />
            </span>
        </span>

        <span class="min-w-0">
            <span class="block truncate font-semibold text-zinc-900 dark:text-white">{{ $user->name }}</span>
            <span class="mt-0.5 block truncate text-sm text-zinc-500">{{ $user->email }}</span>
            <span class="mt-1 block text-xs font-medium text-violet-600 dark:text-violet-300">Click to change photo</span>
        </span>
        <input id="{{ $inputId }}" type="file" wire:model="profilePhoto" accept="image/jpeg,image/png,image/webp" aria-label="Choose profile photo" class="sr-only" data-profile-photo-input>
    </label>

    <div class="flex shrink-0 items-center gap-2">
        @if ($pendingPhoto)
            <button type="button" wire:click="saveProfilePhoto" wire:loading.attr="disabled" wire:target="saveProfilePhoto,profilePhoto" class="inline-flex min-h-9 items-center justify-center rounded-xl bg-violet-600 px-3 text-xs font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-60">
                Save
            </button>
        @endif

        @if ($user->avatar_path)
            <button type="button" wire:click="removeProfilePhoto" wire:loading.attr="disabled" wire:target="removeProfilePhoto" class="inline-flex min-h-9 items-center justify-center rounded-xl border border-zinc-200 px-3 text-xs font-semibold text-zinc-700 transition hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-white/10 dark:text-zinc-200 dark:hover:bg-white/5">
                Remove
            </button>
        @endif
    </div>

    @error('profilePhoto')
        <p class="basis-full text-sm font-medium text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror
</div>
