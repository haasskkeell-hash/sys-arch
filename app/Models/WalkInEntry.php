<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalkInEntry extends Model
{
    protected $fillable = [
        'user_id',
        'queue_number',
        'customer_name',
        'customer_phone',
        'service_type',
        'priority',
        'status',
        'counter_id',
        'notes',
        'checked_in_at',
        'called_at',
        'service_started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_in_at' => 'datetime',
            'called_at' => 'datetime',
            'service_started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<ServiceCounter, $this> */
    public function counter(): BelongsTo
    {
        return $this->belongsTo(ServiceCounter::class, 'counter_id');
    }
}
