<?php

namespace App\Models;

use App\Notifications\BookingStatusChanged;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $assigned_technician_id
 * @property string $reference
 * @property string|null $idempotency_key
 * @property string|null $customer_name
 * @property string|null $customer_phone
 * @property string $service_type
 * @property string $booking_type
 * @property string $status
 * @property string $address
 * @property string|null $description
 * @property Carbon|null $scheduled_at
 * @property bool $is_priority
 * @property string|null $internal_notes
 * @property string|null $cancellation_reason
 * @property string|float|null $latitude
 * @property string|float|null $longitude
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property int $total
 * @property string $day
 */
class Booking extends Model
{
    public const STATUSES = ['pending', 'matching', 'assigned', 'en_route', 'in_progress', 'completed', 'cancelled', 'no_show'];

    public const ACTIVE_STATUSES = ['pending', 'matching', 'assigned', 'en_route', 'in_progress'];

    private const TRANSITIONS = [
        'pending' => ['matching', 'assigned', 'en_route', 'cancelled', 'no_show'],
        'matching' => ['assigned', 'en_route', 'cancelled', 'no_show'],
        'assigned' => ['matching', 'en_route', 'in_progress', 'completed', 'cancelled', 'no_show'],
        'en_route' => ['in_progress', 'completed', 'cancelled', 'no_show'],
        'in_progress' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
        'no_show' => [],
    ];

    protected $fillable = [
        'user_id',
        'assigned_technician_id',
        'reference',
        'idempotency_key',
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

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_technician_id');
    }

    /** @return HasOne<Payment, $this> */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /** @return HasOne<Review, $this> */
    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /** @return HasMany<BookingStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(BookingStatusHistory::class)->latest('created_at');
    }

    /** @return HasOne<Quotation, $this> */
    public function quotation(): HasOne
    {
        return $this->hasOne(Quotation::class);
    }

    /** @return BelongsTo<ServiceCatalog, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(ServiceCatalog::class, 'service_type', 'code');
    }

    /** @param array<string, mixed> $metadata */
    public function recordInitialStatus(?User $actor = null, ?string $reason = null, array $metadata = []): BookingStatusHistory
    {
        abort_unless(in_array($this->status, self::STATUSES, true), 422, 'The booking status is invalid.');

        return $this->statusHistory()->create([
            'from_status' => null,
            'to_status' => $this->status,
            'actor_id' => $actor?->id,
            'reason' => $reason,
            'metadata' => $metadata,
            'idempotency_key' => $this->idempotency_key,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function transitionTo(string $status, ?User $actor = null, ?string $reason = null, array $metadata = [], ?string $idempotencyKey = null): bool
    {
        abort_unless(in_array($status, self::STATUSES, true), 422, 'The booking status is invalid.');

        if ($idempotencyKey !== null && $this->statusHistory()->where('idempotency_key', $idempotencyKey)->exists()) {
            return false;
        }

        if ($this->status === $status) {
            return false;
        }

        abort_unless(
            in_array($status, self::TRANSITIONS[(string) $this->status] ?? [], true),
            422,
            "A booking cannot move from {$this->status} to {$status}.",
        );

        $fromStatus = $this->status;
        $this->forceFill(['status' => $status])->save();
        $this->statusHistory()->create([
            'from_status' => $fromStatus,
            'to_status' => $status,
            'actor_id' => $actor?->id,
            'reason' => $reason,
            'metadata' => $metadata,
            'idempotency_key' => $idempotencyKey,
            'created_at' => now(),
        ]);

        if (Schema::hasTable('notifications')) {
            collect([$this->customer, $this->technician])
                ->filter()
                ->unique('id')
                ->each(function (User $recipient) use ($fromStatus): void {
                    $recipient->notifyNow(new BookingStatusChanged($this, $fromStatus));
                });
        }

        return true;
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
