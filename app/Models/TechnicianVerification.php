<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<int, string>|null $service_categories
 */
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

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return HasMany<TechnicianDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(TechnicianDocument::class, 'verification_id');
    }

    public function supportsService(string $serviceCode): bool
    {
        return $this->status === 'approved'
            && in_array($serviceCode, ServiceCatalog::matchingCodes($this->service_categories ?? []), true);
    }
}
