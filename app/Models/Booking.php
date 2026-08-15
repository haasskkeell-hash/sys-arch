<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Booking extends Model
{
    protected $fillable = [
        'user_id',
        'assigned_technician_id',
        'reference',
        'customer_name',
        'customer_phone',
        'service_type',
        'booking_type',
        'status',
        'address',
        'description',
        'scheduled_at',
        'is_priority',
        'internal_notes',
        'cancellation_reason',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'is_priority' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class, 'service_type', 'code');
    }

    public function ensurePayment(): Payment
    {
        abort_unless($this->status === 'completed', 422, 'Only completed bookings can create a payment record.');

        $amount = ServiceCatalog::query()->where('code', $this->service_type)->value('base_price');

        return $this->payment()->firstOrCreate([], [
            'amount' => $amount !== null ? (float) $amount : 0,
            'status' => 'pending',
        ]);
    }
}
