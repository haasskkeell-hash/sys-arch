<?php

namespace App\Concerns;

use App\Models\User;
use Flux\Flux;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Throwable;

trait HandlesProfilePhoto
{
    use WithFileUploads;

    /** @var mixed */
    public $profilePhoto = null;

    public function saveProfilePhoto(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $this->validate([
            'profilePhoto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $oldPath = $user->avatar_path;
        $newPath = $this->profilePhoto->store('avatars', 'public');

        if (! is_string($newPath)) {
            $this->addError('profilePhoto', __('Unable to save this photo. Please try again.'));

            return;
        }

        try {
            $user->forceFill(['avatar_path' => $newPath])->save();
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($newPath);

            throw $exception;
        }

        if (filled($oldPath) && $oldPath !== $newPath) {
            Storage::disk('public')->delete($oldPath);
        }

        $this->profilePhoto = null;

        Flux::toast(variant: 'success', text: __('Profile photo updated.'));
    }

    public function removeProfilePhoto(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        $oldPath = $user->avatar_path;

        if (blank($oldPath)) {
            return;
        }

        $user->forceFill(['avatar_path' => null])->save();
        Storage::disk('public')->delete($oldPath);
        $this->profilePhoto = null;

        Flux::toast(variant: 'success', text: __('Profile photo removed.'));
    }
}
