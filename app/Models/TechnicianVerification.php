<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TechnicianVerification extends Model
{
    protected $fillable = [
        'user_id',
        'reviewer_id',
        'status',
        'risk_level',
        'service_categories',
        'years_experience',
        'service_area',
        'phone',
        'address',
        'risk_flags',
        'reviewer_notes',
        'decision_reason',
        'submitted_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'service_categories' => 'array',
            'risk_flags' => 'array',
            'years_experience' => 'integer',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TechnicianDocument::class, 'verification_id');
    }
}
