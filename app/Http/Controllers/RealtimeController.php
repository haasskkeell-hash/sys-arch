<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\TechnicianDocument;
use App\Models\TechnicianVerification;
use App\Models\User;
use App\Models\WalkInEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class RealtimeController extends Controller
{
    /** @var array<int, string> */
    private const SCOPES = [
        'admin-dashboard',
        'admin-dispatch-monitor',
        'admin-walk-in-queue',
        'admin-service-bookings',
        'admin-payments-revenue',
        'admin-technician-verification',
        'technician-overview',
        'technician-job-requests',
        'technician-my-jobs',
        'technician-dispatch-routes',
        'technician-earnings',
        'technician-verification-profile',
        'customer-overview',
        'customer-my-bookings',
        'customer-active-booking',
        'customer-walk-in-queue',
        'customer-payments',
    ];

    public function snapshot(Request $request, string $scope): JsonResponse
    {
        abort_unless(in_array($scope, self::SCOPES, true), 404);

        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $this->authorizeScope($scope, $user);

        $version = $this->versionFor($scope, $user);
        $since = trim((string) $request->query('since', ''));
        $changed = $since === '' || $since !== $version;

        $payload = [
            'scope' => $scope,
            'version' => $version,
            'changed' => $changed,
        ];

        if ($changed) {
            if ($scope === 'customer-active-booking') {
                $payload['tracking'] = $this->customerActiveBookingTracking($user);
            } else {
                $payload['counts'] = $this->countsFor($scope, $user, $version);
            }
        }

        return response()->json($payload);
    }

    private function authorizeScope(string $scope, User $user): void
    {
        abort_unless($user->hasActiveAccount(), 403);

        if (str_starts_with($scope, 'admin-')) {
            abort_unless($user->isAdmin(), 403);

            return;
        }

        if (str_starts_with($scope, 'technician-')) {
            abort_unless($user->isTechnician(), 403);

            return;
        }

        abort_unless($user->isCustomer(), 403);
    }

    private function versionFor(string $scope, User $user): string
    {
        return match ($scope) {
            'admin-dashboard' => $this->latestVersion([
                $this->latestUpdatedAt('bookings'),
                $this->latestUpdatedAt('payments'),
                $this->latestUpdatedAt('technician_verifications'),
            ]),
            'admin-dispatch-monitor' => $this->latestVersion([
                $this->latestUpdatedAt('bookings'),
                $this->latestUpdatedAt('users'),
            ]),
            'admin-service-bookings' => $this->latestUpdatedAt('bookings'),
            'admin-walk-in-queue' => $this->latestVersion([
                $this->latestUpdatedAt('walk_in_entries'),
                $this->latestUpdatedAt('service_counters'),
            ]),
            'admin-payments-revenue' => $this->latestVersion([
                $this->latestUpdatedAt('payments'),
                $this->latestUpdatedAt('bookings'),
            ]),
            'admin-technician-verification' => $this->latestVersion([
                $this->latestUpdatedAt('technician_verifications'),
                $this->latestUpdatedAt('technician_documents'),
            ]),
            'technician-overview' => $this->latestVersion([
                $this->latestTechnicianBookingUpdatedAt($user, true),
                $this->latestTechnicianBookingUpdatedAt($user, false),
            ]),
            'technician-job-requests' => $this->latestTechnicianBookingUpdatedAt($user, false),
            'technician-my-jobs', 'technician-dispatch-routes' => $this->latestTechnicianBookingUpdatedAt($user, true),
            'technician-earnings' => $this->latestTechnicianPaymentUpdatedAt($user),
            'technician-verification-profile' => $this->latestTechnicianVerificationUpdatedAt($user),
            'customer-overview', 'customer-my-bookings' => $this->latestUpdatedAtForCustomerBookings($user),
            'customer-active-booking' => $this->latestActiveBookingTrackingUpdatedAt($user),
            'customer-walk-in-queue' => $this->latestUpdatedAtForCustomerQueue($user),
            'customer-payments' => $this->latestCustomerPaymentUpdatedAt($user),
            default => abort(404),
        };
    }

    /** @return array<string, int> */
    private function countsFor(string $scope, User $user, string $version): array
    {
        $owner = $this->isGlobalScope($scope) ? 'global' : (string) $user->id;
        $key = "fixtrack:realtime:{$scope}:{$owner}:{$version}";

        return Cache::remember($key, now()->addSeconds($this->cacheTtl($scope)), function () use ($scope, $user): array {
            return match ($scope) {
                'admin-dashboard' => $this->adminDashboardCounts(),
                'admin-dispatch-monitor' => $this->adminDispatchCounts(),
                'admin-walk-in-queue' => $this->adminWalkInCounts(),
                'admin-service-bookings' => $this->adminBookingCounts(),
                'admin-payments-revenue' => $this->adminPaymentCounts(),
                'admin-technician-verification' => $this->adminVerificationCounts(),
                'technician-overview' => $this->technicianOverviewCounts($user),
                'technician-job-requests' => $this->technicianRequestCounts(),
                'technician-my-jobs' => $this->technicianJobCounts($user),
                'technician-dispatch-routes' => $this->technicianDispatchCounts($user),
                'technician-earnings' => $this->technicianEarningsCounts($user),
                'technician-verification-profile' => $this->technicianVerificationCounts($user),
                'customer-overview', 'customer-my-bookings' => $this->customerBookingCounts($user),
                'customer-walk-in-queue' => $this->customerQueueCounts($user),
                'customer-payments' => $this->customerPaymentCounts($user),
                default => abort(404),
            };
        });
    }

    /** @return array<string, int> */
    private function adminDashboardCounts(): array
    {
        $counts = $this->bookingStatusCounts();
        $total = array_sum($counts);
        $completed = $counts['completed'] ?? 0;

        return [
            'active' => $this->sumStatuses($counts, ['pending', 'matching', 'assigned', 'en_route', 'in_progress']),
            'today' => $this->tableExists('bookings') ? Booking::query()->whereDate('created_at', today())->count() : 0,
            'completed' => $completed,
            'completion_rate' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'action_required' => $this->sumStatuses($counts, ['pending', 'matching']),
        ];
    }

    /** @return array<string, int> */
    private function adminDispatchCounts(): array
    {
        if (! $this->tableExists('bookings')) {
            return ['unassigned' => 0, 'active' => 0];
        }

        return [
            'unassigned' => Booking::query()->whereNull('assigned_technician_id')->whereIn('status', ['pending', 'matching'])->count(),
            'active' => Booking::query()->whereIn('status', ['assigned', 'en_route', 'in_progress'])->count(),
        ];
    }

    /** @return array<string, int> */
    private function adminWalkInCounts(): array
    {
        $counts = $this->walkInStatusCounts();

        return [
            'waiting' => $counts['waiting'] ?? 0,
            'serving' => $this->sumStatuses($counts, ['called', 'in_service', 'serving']),
            'completed' => $counts['completed'] ?? 0,
        ];
    }

    /** @return array<string, int> */
    private function adminBookingCounts(): array
    {
        $counts = $this->bookingStatusCounts();

        return [
            'total' => array_sum($counts),
            'active' => $this->sumStatuses($counts, ['pending', 'matching', 'assigned', 'en_route', 'in_progress']),
            'unassigned' => $this->adminDispatchCounts()['unassigned'] ?? 0,
            'completed' => $counts['completed'] ?? 0,
        ];
    }

    /** @return array<string, int> */
    private function adminPaymentCounts(): array
    {
        $counts = $this->paymentStatusCounts();

        return [
            'pending' => $counts['pending'] ?? 0,
            'paid' => $counts['paid'] ?? 0,
            'failed' => $counts['failed'] ?? 0,
            'refunded' => $counts['refunded'] ?? 0,
        ];
    }

    /** @return array<string, int> */
    private function adminVerificationCounts(): array
    {
        if (! $this->tableExists('technician_verifications')) {
            return ['pending' => 0, 'requested' => 0, 'approved' => 0, 'high_risk' => 0];
        }

        $counts = $this->statusCounts('technician_verifications');

        return [
            'pending' => $this->sumStatuses($counts, ['submitted', 'under_review']),
            'requested' => $counts['information_requested'] ?? 0,
            'approved' => $counts['approved'] ?? 0,
            'high_risk' => TechnicianVerification::query()->where('risk_level', 'high')->count(),
        ];
    }

    /** @return array<string, int> */
    private function technicianOverviewCounts(User $technician): array
    {
        $requests = $this->technicianRequestCounts();
        $jobs = $this->technicianJobCounts($technician);

        return [
            'incoming' => $requests['incoming'],
            'active' => $jobs['active'],
            'completed' => $jobs['completed'],
        ];
    }

    /** @return array<string, int> */
    private function technicianRequestCounts(): array
    {
        if (! $this->tableExists('bookings')) {
            return ['incoming' => 0];
        }

        return [
            'incoming' => Booking::query()->whereNull('assigned_technician_id')->whereIn('status', ['pending', 'matching'])->count(),
        ];
    }

    /** @return array<string, int> */
    private function technicianJobCounts(User $technician): array
    {
        if (! $this->tableExists('bookings')) {
            return ['active' => 0, 'completed' => 0];
        }

        $counts = Booking::query()->where('assigned_technician_id', $technician->id)->pluck('status')->countBy()->all();

        return [
            'active' => $this->sumStatuses($counts, ['assigned', 'en_route', 'in_progress']),
            'completed' => $counts['completed'] ?? 0,
        ];
    }

    /** @return array<string, int> */
    private function technicianDispatchCounts(User $technician): array
    {
        return ['active' => $this->technicianJobCounts($technician)['active']];
    }

    /** @return array<string, int> */
    private function technicianEarningsCounts(User $technician): array
    {
        if (! $this->tableExists('payments') || ! $this->tableExists('bookings')) {
            return ['paid' => 0, 'pending' => 0];
        }

        $counts = Payment::query()
            ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->where('assigned_technician_id', $technician->id))
            ->pluck('status')
            ->countBy()
            ->all();

        return [
            'paid' => $counts['paid'] ?? 0,
            'pending' => $counts['pending'] ?? 0,
        ];
    }

    /** @return array<string, int> */
    private function technicianVerificationCounts(User $technician): array
    {
        if (! $this->tableExists('technician_verifications')) {
            return ['submitted' => 0, 'approved' => 0, 'rejected' => 0];
        }

        $counts = TechnicianVerification::query()
            ->where('user_id', $technician->id)
            ->pluck('status')
            ->countBy()
            ->all();

        return [
            'submitted' => $counts['submitted'] ?? 0,
            'approved' => $counts['approved'] ?? 0,
            'rejected' => $counts['rejected'] ?? 0,
        ];
    }

    /** @return array<string, int> */
    private function customerBookingCounts(User $customer): array
    {
        if (! $this->tableExists('bookings')) {
            return ['active' => 0, 'completed' => 0, 'cancelled' => 0];
        }

        $counts = Booking::query()->where('user_id', $customer->id)->pluck('status')->countBy()->all();

        return [
            'active' => $this->sumStatuses($counts, ['pending', 'matching', 'assigned', 'en_route', 'in_progress']),
            'completed' => $counts['completed'] ?? 0,
            'cancelled' => $counts['cancelled'] ?? 0,
        ];
    }

    /** @return array<string, int> */
    private function customerQueueCounts(User $customer): array
    {
        if (! $this->tableExists('walk_in_entries') || ! $this->columnExists('walk_in_entries', 'user_id')) {
            return ['waiting' => 0, 'serving' => 0, 'completed' => 0];
        }

        $counts = WalkInEntry::query()->where('user_id', $customer->id)->pluck('status')->countBy()->all();

        return [
            'waiting' => $counts['waiting'] ?? 0,
            'serving' => $this->sumStatuses($counts, ['called', 'in_service', 'serving']),
            'completed' => $counts['completed'] ?? 0,
        ];
    }

    /** @return array<string, int> */
    private function statusCounts(string $table): array
    {
        if (! $this->tableExists($table)) {
            return [];
        }

        $models = [
            'bookings' => Booking::class,
            'payments' => Payment::class,
            'technician_verifications' => TechnicianVerification::class,
            'walk_in_entries' => WalkInEntry::class,
        ];
        $model = $models[$table] ?? null;

        if ($model === null) {
            return [];
        }

        return $model::query()
            ->select('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn (mixed $total): int => (int) $total)
            ->all();
    }

    /** @return array<string, int> */
    private function bookingStatusCounts(): array
    {
        return $this->statusCounts('bookings');
    }

    /** @return array<string, int> */
    private function walkInStatusCounts(): array
    {
        return $this->statusCounts('walk_in_entries');
    }

    /** @return array<string, int> */
    private function paymentStatusCounts(): array
    {
        return $this->statusCounts('payments');
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<int, string>  $statuses
     */
    private function sumStatuses(array $counts, array $statuses): int
    {
        return collect($statuses)->sum(fn (string $status): int => $counts[$status] ?? 0);
    }

    private function latestTechnicianBookingUpdatedAt(User $technician, bool $assigned): string
    {
        if (! $this->tableExists('bookings')) {
            return 'none';
        }

        $query = Booking::query();

        if ($assigned) {
            $query->where('assigned_technician_id', $technician->id);
        } else {
            $query->whereNull('assigned_technician_id')->whereIn('status', ['pending', 'matching']);
        }

        return $this->formatTimestamp($query->max('updated_at'));
    }

    private function latestUpdatedAtForCustomerBookings(User $customer): string
    {
        if (! $this->tableExists('bookings')) {
            return 'none';
        }

        return $this->formatTimestamp(Booking::query()->where('user_id', $customer->id)->max('updated_at'));
    }

    private function latestActiveBookingTrackingUpdatedAt(User $customer): string
    {
        if (! $this->tableExists('bookings')) {
            return 'none';
        }

        $booking = Booking::query()
            ->select(['id', 'assigned_technician_id', 'updated_at'])
            ->with('technician:id,updated_at')
            ->whereBelongsTo($customer, 'customer')
            ->whereIn('status', ['pending', 'matching', 'assigned', 'en_route', 'in_progress'])
            ->latest('created_at')
            ->first();

        return $this->latestVersion([
            $this->formatTimestamp($booking?->updated_at),
            $this->formatTimestamp($booking?->technician?->updated_at),
        ]);
    }

    /** @return array<string, mixed>|null */
    private function customerActiveBookingTracking(User $customer): ?array
    {
        if (! $this->tableExists('bookings')) {
            return null;
        }

        $technicianColumns = ['id', 'name', 'updated_at'];
        foreach (['avatar_path', 'latitude', 'longitude', 'last_seen_at'] as $column) {
            if ($this->columnExists('users', $column)) {
                $technicianColumns[] = $column;
            }
        }

        $booking = Booking::query()
            ->select(['id', 'assigned_technician_id', 'reference', 'service_type', 'status', 'address', 'latitude', 'longitude'])
            ->with(['technician' => fn ($query) => $query->select($technicianColumns)])
            ->whereBelongsTo($customer, 'customer')
            ->whereIn('status', ['pending', 'matching', 'assigned', 'en_route', 'in_progress'])
            ->latest('created_at')
            ->first();

        if (! $booking) {
            return null;
        }

        $technician = in_array($booking->status, ['assigned', 'en_route', 'in_progress'], true)
            ? $booking->technician
            : null;

        return [
            'booking_id' => $booking->id,
            'reference' => $booking->reference,
            'service_type' => $booking->service_type,
            'status' => $booking->status,
            'address' => $booking->address,
            'customer' => [
                'latitude' => $booking->latitude !== null ? (float) $booking->latitude : null,
                'longitude' => $booking->longitude !== null ? (float) $booking->longitude : null,
            ],
            'technician' => $technician ? [
                'id' => $technician->id,
                'name' => $technician->name,
                'avatar' => $technician->avatarUrl(),
                'initials' => $technician->initials(),
                'latitude' => $technician->latitude !== null ? (float) $technician->latitude : null,
                'longitude' => $technician->longitude !== null ? (float) $technician->longitude : null,
                'last_seen_at' => $technician->last_seen_at?->toISOString(),
            ] : null,
        ];
    }

    private function latestUpdatedAtForCustomerQueue(User $customer): string
    {
        if (! $this->tableExists('walk_in_entries') || ! $this->columnExists('walk_in_entries', 'user_id')) {
            return 'none';
        }

        return $this->formatTimestamp(WalkInEntry::query()->where('user_id', $customer->id)->max('updated_at'));
    }

    private function latestTechnicianPaymentUpdatedAt(User $technician): string
    {
        if (! $this->tableExists('bookings')) {
            return 'none';
        }

        $bookingUpdatedAt = Booking::query()
            ->where('assigned_technician_id', $technician->id)
            ->max('updated_at');

        $paymentUpdatedAt = $this->tableExists('payments')
            ? Payment::query()
                ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->where('assigned_technician_id', $technician->id))
                ->max('payments.updated_at')
            : null;

        return $this->latestVersion([
            $this->formatTimestamp($bookingUpdatedAt),
            $this->formatTimestamp($paymentUpdatedAt),
        ]);
    }

    private function latestTechnicianVerificationUpdatedAt(User $technician): string
    {
        if (! $this->tableExists('technician_verifications')) {
            return 'none';
        }

        $verificationUpdatedAt = TechnicianVerification::query()
            ->where('user_id', $technician->id)
            ->max('updated_at');
        $documentUpdatedAt = $this->tableExists('technician_documents')
            ? TechnicianDocument::query()
                ->whereHas('verification', fn (Builder $verificationQuery): Builder => $verificationQuery->where('user_id', $technician->id))
                ->max('technician_documents.updated_at')
            : null;

        return $this->latestVersion([
            $this->formatTimestamp($verificationUpdatedAt),
            $this->formatTimestamp($documentUpdatedAt),
        ]);
    }

    private function latestCustomerPaymentUpdatedAt(User $customer): string
    {
        if (! $this->tableExists('bookings')) {
            return 'none';
        }

        $bookingUpdatedAt = Booking::query()->where('user_id', $customer->id)->max('updated_at');
        $paymentUpdatedAt = $this->tableExists('payments')
            ? Payment::query()
                ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->where('user_id', $customer->id))
                ->max('payments.updated_at')
            : null;

        return $this->latestVersion([
            $this->formatTimestamp($bookingUpdatedAt),
            $this->formatTimestamp($paymentUpdatedAt),
        ]);
    }

    /** @return array<string, int> */
    private function customerPaymentCounts(User $customer): array
    {
        if (! $this->tableExists('payments') || ! $this->tableExists('bookings')) {
            return ['paid' => 0, 'pending' => 0, 'failed' => 0, 'refunded' => 0];
        }

        $counts = Payment::query()
            ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->where('user_id', $customer->id))
            ->pluck('status')
            ->countBy()
            ->all();

        return [
            'paid' => $counts['paid'] ?? 0,
            'pending' => $counts['pending'] ?? 0,
            'failed' => $counts['failed'] ?? 0,
            'refunded' => $counts['refunded'] ?? 0,
        ];
    }

    private function latestUpdatedAt(string $table): string
    {
        if (! $this->tableExists($table) || ! $this->columnExists($table, 'updated_at')) {
            return 'none';
        }

        $models = [
            'bookings' => Booking::class,
            'payments' => Payment::class,
            'technician_verifications' => TechnicianVerification::class,
            'technician_documents' => TechnicianDocument::class,
            'walk_in_entries' => WalkInEntry::class,
            'users' => User::class,
        ];
        $model = $models[$table] ?? null;

        return $model === null ? 'none' : $this->formatTimestamp($model::query()->max('updated_at'));
    }

    /** @param array<int, string> $versions */
    private function latestVersion(array $versions): string
    {
        return collect($versions)
            ->reject(fn (string $version): bool => $version === 'none')
            ->sortDesc()
            ->first() ?? 'none';
    }

    private function formatTimestamp(mixed $value): string
    {
        return $value ? Carbon::parse($value)->toISOString() : 'none';
    }

    private function tableExists(string $table): bool
    {
        return Schema::hasTable($table);
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->tableExists($table) && Schema::hasColumn($table, $column);
    }

    private function isGlobalScope(string $scope): bool
    {
        return str_starts_with($scope, 'admin-') || $scope === 'technician-job-requests';
    }

    private function cacheTtl(string $scope): int
    {
        return in_array($scope, ['admin-dispatch-monitor', 'admin-walk-in-queue', 'technician-job-requests', 'customer-walk-in-queue'], true)
            ? 10
            : 30;
    }
}
