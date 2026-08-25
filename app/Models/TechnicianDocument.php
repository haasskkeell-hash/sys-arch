<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicianDocument extends Model
{
    protected $fillable = [
        'verification_id',
        'type',
        'label',
        'status',
        'masked_number',
        'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'date'];
    }

    /** @return BelongsTo<TechnicianVerification, $this> */
    public function verification(): BelongsTo
    {
        return $this->belongsTo(TechnicianVerification::class);
    }
}
