<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Quotation extends Model
{
    protected $fillable = [
        'booking_id',
        'technician_id',
        'assessment_notes',
        'labor_amount',
        'materials_amount',
        'total_amount',
        'status',
        'sent_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'labor_amount' => 'decimal:2',
            'materials_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'sent_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Booking, $this> */
    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }
}
