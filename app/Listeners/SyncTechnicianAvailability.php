<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Schema;

class SyncTechnicianAvailability
{
    public function handleLogin(Login $event): void
    {
        $this->sync($event->user, 'available');
    }

    public function handleLogout(Logout $event): void
    {
        $this->sync($event->user, 'offline');
    }

    private function sync(mixed $user, string $availabilityStatus): void
    {
        if (! $user instanceof User || ! $user->isTechnician() || ! Schema::hasColumn((new User)->getTable(), 'availability_status')) {
            return;
        }

        $user->forceFill(['availability_status' => $availabilityStatus])->saveQuietly();
    }
}
