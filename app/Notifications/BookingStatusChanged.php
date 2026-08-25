<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class BookingStatusChanged extends Notification
{
    public function __construct(public Booking $booking, public string $fromStatus) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'reference' => $this->booking->reference,
            'from_status' => $this->fromStatus,
            'status' => $this->booking->status,
            'title' => 'Booking '.Str::headline($this->booking->status),
            'detail' => $this->booking->reference.' · '.$this->booking->address,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
