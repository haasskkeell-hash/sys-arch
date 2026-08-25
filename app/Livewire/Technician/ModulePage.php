<?php

namespace App\Livewire\Technician;

use App\Concerns\HandlesProfilePhoto;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Quotation;
use App\Models\Review;
use App\Models\ServiceCatalog;
use App\Models\SupportTicket;
use App\Models\TechnicianRequestDecline;
use App\Models\TechnicianVerification;
use App\Models\User;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Number;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

#[Layout('layouts.app')]
#[Title('Technician Workspace')]
class ModulePage extends Component
{
    use HandlesProfilePhoto;
    use WithPagination;

    public string $moduleSlug = 'overview';

    public string $requestSearch = '';

    public string $jobSearch = '';

    public string $jobStatus = 'all';

    public string $scheduleSearch = '';

    public string $scheduleRange = 'upcoming';

    public bool $showConfirmation = false;

    public string $confirmationTitle = '';

    public string $confirmationDescription = '';

    public string $confirmationActionLabel = 'Confirm';

    public string $confirmationVariant = 'danger';

    #[Locked]
    public ?int $quotationBookingId = null;

    public bool $showQuotation = false;

    public string $assessmentNotes = '';

    public string $laborAmount = '';

    public string $materialsAmount = '';

    /** @var array{operation: string, parameters: array<int, mixed>, idempotency_key: string}|null */
    #[Locked]
    public ?array $pendingConfirmation = null;

    /** @var array<string, bool> */
    private array $tableAvailability = [];

    /** @var array<string, bool> */
    private array $columnAvailability = [];

    /**
     * @return array<string, array{label: string, description: string, icon: string, group: string}>
     */
    public static function modules(): array
    {
        return [
            'overview' => [
                'label' => 'Dashboard',
                'description' => 'Your live jobs, performance, and service activity.',
                'icon' => 'home',
                'group' => 'Technician Workspace',
            ],
            'job-requests' => [
                'label' => 'Job Requests',
                'description' => 'Review and accept available customer service requests.',
                'icon' => 'queue-list',
                'group' => 'Technician Workspace',
            ],
            'my-jobs' => [
                'label' => 'My Jobs',
                'description' => 'Manage assigned service requests and job progress.',
                'icon' => 'clipboard-document-list',
                'group' => 'Technician Workspace',
            ],
            'schedule' => [
                'label' => 'Schedule',
                'description' => 'Review upcoming appointments and service timing.',
                'icon' => 'calendar-days',
                'group' => 'Technician Workspace',
            ],
            'dispatch-routes' => [
                'label' => 'Dispatch & Routes',
                'description' => 'Follow live assignments, locations, and routes.',
                'icon' => 'map',
                'group' => 'Operations',
            ],
            'earnings' => [
                'label' => 'Earnings',
                'description' => 'Review completed-job earnings and payment status.',
                'icon' => 'banknotes',
                'group' => 'Finance',
            ],
            'ratings-reviews' => [
                'label' => 'Ratings & Reviews',
                'description' => 'Track customer feedback and service quality.',
                'icon' => 'star',
                'group' => 'Performance',
            ],
            'verification-profile' => [
                'label' => 'Verification & Profile',
                'description' => 'Review verification status, skills, and service areas.',
                'icon' => 'identification',
                'group' => 'Account',
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'Review job assignments, updates, and announcements.',
                'icon' => 'bell',
                'group' => 'Account',
            ],
            'support' => [
                'label' => 'Support & Help',
                'description' => 'Access service support and review help requests.',
                'icon' => 'chat-bubble-left-right',
                'group' => 'Account',
            ],
        ];
    }

    public function mount(?string $module = null): void
    {
        $this->authorizeTechnician();
        $module ??= 'overview';
        abort_unless(array_key_exists($module, self::modules()), 404);

        $this->moduleSlug = $module;
    }

    public function render(): View
    {
        return view('livewire.technician.module-page', [
            'module' => self::modules()[$this->moduleSlug],
            'moduleState' => $this->moduleState(),
            'content' => $this->contentFor($this->moduleSlug),
            'locationTrackingActive' => $this->locationTrackingActive(),
        ]);
    }

    public function updatingRequestSearch(): void
    {
        $this->resetPage('requestsPage');
    }

    public function updatingJobSearch(): void
    {
        $this->resetPage('jobsPage');
    }

    public function updatingJobStatus(): void
    {
        $this->resetPage('jobsPage');
    }

    public function updatingScheduleSearch(): void
    {
        $this->resetPage('schedulePage');
    }

    public function updatingScheduleRange(): void
    {
        $this->resetPage('schedulePage');
    }

    public function setAvailability(string $status): void
    {
        $this->authorizeTechnician();
        $this->validateValue($status, ['available', 'offline']);
        abort_unless($this->columnExists('users', 'availability_status'), 422, 'Technician availability is not available yet.');

        DB::transaction(function () use ($status): void {
            $technician = User::query()->lockForUpdate()->findOrFail(auth()->id());
            abort_if(
                $status === 'offline'
                && Booking::query()->whereBelongsTo($technician, 'technician')->whereIn('status', Booking::ACTIVE_STATUSES)->exists(),
                409,
                'Complete your active jobs before going offline.',
            );

            $technician->update([
                'availability_status' => $status,
            ]);
        });

        $this->recordAudit('technician.availability_updated', 'user', (int) auth()->id(), ['availability_status' => $status]);
        $this->successToast($status === 'available' ? 'You are now online.' : 'You are now offline.');
    }

    public function requestConfirmation(string $action, int $id = 0): void
    {
        $this->authorizeTechnician();
        $confirmation = $this->confirmationDefinition($action, $id);

        $this->pendingConfirmation = [
            'operation' => $confirmation['operation'],
            'parameters' => $confirmation['parameters'],
            'idempotency_key' => Str::uuid()->toString(),
        ];
        $this->confirmationTitle = $confirmation['title'];
        $this->confirmationDescription = $confirmation['description'];
        $this->confirmationActionLabel = $confirmation['label'];
        $this->confirmationVariant = $confirmation['variant'];
        $this->showConfirmation = true;
    }

    public function executeConfirmedAction(): void
    {
        $this->authorizeTechnician();
        $pending = $this->pendingConfirmation;

        if ($pending === null) {
            return;
        }

        $this->cancelConfirmation();

        try {
            $this->dispatchConfirmedAction($pending);
        } catch (Throwable $exception) {
            $this->notifyActionFailure($exception);
        }
    }

    public function cancelConfirmation(): void
    {
        $this->showConfirmation = false;
        $this->pendingConfirmation = null;
        $this->confirmationTitle = '';
        $this->confirmationDescription = '';
        $this->confirmationActionLabel = 'Confirm';
        $this->confirmationVariant = 'danger';
    }

    public function acceptRequest(int $bookingId, ?string $idempotencyKey = null): void
    {
        $this->authorizeTechnician();
        $idempotencyKey = $idempotencyKey !== null ? $this->idempotencyKey($idempotencyKey) : null;

        DB::transaction(function () use ($bookingId, $idempotencyKey): void {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);
            abort_unless($booking !== null, 404);
            $technician = User::query()->lockForUpdate()->findOrFail(auth()->id());

            abort_unless($technician->isTechnician() && $technician->hasActiveAccount(), 403, 'Your technician account is not active.');

            if ($idempotencyKey !== null && $booking->statusHistory()->where('idempotency_key', $idempotencyKey)->exists()) {
                abort_unless((int) $booking->assigned_technician_id === (int) $technician->id, 403);

                return;
            }

            abort_if($booking->assigned_technician_id !== null || ! in_array($booking->status, ['pending', 'matching'], true), 409, 'This request is no longer available.');
            abort_unless($technician->availability_status === 'available', 409, 'Go online before accepting requests.');

            $verification = $this->tableExists('technician_verifications')
                ? TechnicianVerification::query()->whereBelongsTo($technician, 'technician')->where('status', 'approved')->first()
                : null;
            abort_unless($verification !== null, 403, 'Your technician verification must be approved before accepting requests.');
            abort_unless(
                $verification->supportsService((string) $booking->service_type),
                409,
                'This request is outside your approved service categories.',
            );

            $maxActiveJobs = max(1, (int) ($this->tableExists('platform_settings') ? PlatformSetting::query()->where('key', 'max_active_jobs')->value('value') : 1));
            abort_if(
                Booking::query()->whereBelongsTo($technician, 'technician')->whereIn('status', Booking::ACTIVE_STATUSES)->count() >= $maxActiveJobs,
                409,
                'You have reached your active job limit.',
            );
            abort_if(
                $booking->scheduled_at !== null
                && Booking::query()->whereBelongsTo($technician, 'technician')->whereIn('status', Booking::ACTIVE_STATUSES)->where('scheduled_at', $booking->scheduled_at)->exists(),
                409,
                'You already have a job scheduled at this time.',
            );
            abort_if(
                $this->tableExists('technician_request_declines')
                && TechnicianRequestDecline::query()->whereBelongsTo($technician, 'technician')->whereBelongsTo($booking, 'booking')->exists(),
                409,
                'You already declined this request.',
            );

            $booking->update([
                'assigned_technician_id' => $technician->id,
            ]);
            $booking->transitionTo('en_route', $technician, 'Accepted request.', ['source' => 'technician'], $idempotencyKey);

            if ($this->columnExists('users', 'availability_status')) {
                $technician->update(['availability_status' => 'busy']);
            }
        });

        $this->recordAudit('technician.request_accepted', 'booking', $bookingId);
        $this->successToast('Job accepted. You are now en route.');
        $this->redirectRoute('technician.module', navigate: true);
    }

    public function declineRequest(int $bookingId): void
    {
        $this->authorizeTechnician();
        abort_unless($this->tableExists('technician_request_declines'), 422, 'Request decline tracking is not available yet.');

        DB::transaction(function () use ($bookingId): void {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);
            abort_unless($booking !== null, 404);
            abort_if($booking->assigned_technician_id !== null || ! in_array($booking->status, ['pending', 'matching'], true), 409, 'This request is no longer available.');

            TechnicianRequestDecline::query()->firstOrCreate([
                'technician_id' => auth()->id(),
                'booking_id' => $bookingId,
            ]);
        });

        $this->recordAudit('technician.request_declined', 'booking', $bookingId);
        $this->successToast('Request removed from your queue.');
    }

    public function cancelBooking(int $bookingId, ?string $idempotencyKey = null): void
    {
        $this->authorizeTechnician();
        $idempotencyKey = $idempotencyKey !== null ? $this->idempotencyKey($idempotencyKey) : null;

        DB::transaction(function () use ($bookingId, $idempotencyKey): void {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);
            abort_unless($booking !== null, 404);
            $technician = User::query()->lockForUpdate()->findOrFail(auth()->id());

            $history = $idempotencyKey !== null
                ? $booking->statusHistory()->where('idempotency_key', $idempotencyKey)->first()
                : null;

            if ($history !== null) {
                abort_unless((int) $history->actor_id === (int) $technician->id, 403);

                return;
            }

            abort_unless((int) $booking->assigned_technician_id === (int) $technician->id, 403);
            abort_unless(in_array($booking->status, Booking::ACTIVE_STATUSES, true), 409, 'This job can no longer be cancelled.');

            $reason = 'Cancelled by technician.';
            $booking->update([
                'cancellation_reason' => $reason,
            ]);
            $booking->transitionTo('cancelled', $technician, $reason, ['source' => 'technician'], $idempotencyKey);
            $booking->update([
                'assigned_technician_id' => null,
            ]);

            if (! Booking::query()->whereBelongsTo($technician, 'technician')->whereIn('status', Booking::ACTIVE_STATUSES)->exists()) {
                $technician->update(['availability_status' => 'available']);
            }
        });

        $this->recordAudit('technician.booking_cancelled', 'booking', $bookingId, [
            'reason' => 'Cancelled by technician.',
        ]);
        $this->successToast('Job cancelled. You are available for the next request.');
    }

    public function updateBookingStatus(int $bookingId, string $status, ?string $idempotencyKey = null): void
    {
        $this->authorizeTechnician();
        $this->validateValue($status, ['en_route', 'in_progress', 'completed']);
        $idempotencyKey = $idempotencyKey !== null ? $this->idempotencyKey($idempotencyKey) : null;
        $paymentId = null;
        $paymentCreated = false;

        DB::transaction(function () use ($bookingId, $status, $idempotencyKey, &$paymentId, &$paymentCreated): void {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);
            abort_unless($booking !== null, 404);
            $technician = User::query()->lockForUpdate()->findOrFail(auth()->id());

            abort_unless((int) $booking->assigned_technician_id === (int) $technician->id, 403);

            if ($idempotencyKey !== null && $booking->statusHistory()->where('idempotency_key', $idempotencyKey)->exists()) {
                return;
            }

            abort_if($status === 'en_route' && $booking->status !== 'assigned', 422, 'Only assigned jobs can start a route.');
            abort_if($status === 'in_progress' && ! in_array($booking->status, ['assigned', 'en_route'], true), 422, 'Only assigned jobs can be started.');
            abort_if($status === 'completed' && ! in_array($booking->status, ['en_route', 'in_progress'], true), 422, 'Only active jobs can be completed.');

            $booking->transitionTo($status, $technician, match ($status) {
                'en_route' => 'Route started.',
                'in_progress' => 'Repair started.',
                default => 'Job completed.',
            }, ['source' => 'technician'], $idempotencyKey);

            if ($status === 'completed' && $this->tableExists('payments')) {
                $payment = Booking::query()->findOrFail($bookingId)->ensurePayment();
                $paymentId = (int) $payment->id;
                $paymentCreated = $payment->wasRecentlyCreated;
            }

            if ($status === 'completed' && ! Booking::query()->whereBelongsTo($technician, 'technician')->whereIn('status', Booking::ACTIVE_STATUSES)->exists()) {
                $technician->update(['availability_status' => 'available']);
            }
        });

        $this->recordAudit('technician.booking_updated', 'booking', $bookingId, ['status' => $status]);
        if ($paymentCreated) {
            $this->recordAudit('payment.created', 'payment', $paymentId, ['booking_id' => $bookingId, 'status' => 'pending']);
        }
        $this->successToast(match ($status) {
            'en_route' => 'Route started.',
            'in_progress' => 'Job started.',
            default => $paymentCreated ? 'Job completed and payment record created.' : 'Job completed.',
        });
    }

    public function updateLocation(float $latitude, float $longitude): void
    {
        $this->authorizeTechnician();
        abort_unless($this->locationTrackingActive(), 409, 'Location updates require an active assignment.');

        $validated = Validator::make([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ], [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ])->validate();

        auth()->user()->forceFill([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'last_seen_at' => now(),
        ])->save();
    }

    public function openQuotation(int $bookingId): void
    {
        $this->authorizeTechnician();
        abort_unless($this->tableExists('quotations'), 422, 'Quotations are not available yet.');

        $booking = $this->assignedBookingsQuery()->whereKey($bookingId)->firstOrFail();
        abort_unless(in_array($booking->status, ['assigned', 'en_route', 'in_progress'], true), 422, 'Only active jobs can have a quotation.');

        $quotation = Quotation::query()->where('booking_id', $booking->id)->first();
        $this->quotationBookingId = $booking->id;
        $this->assessmentNotes = $quotation !== null ? (string) $quotation->assessment_notes : '';
        $this->laborAmount = $quotation?->labor_amount !== null ? (string) $quotation->labor_amount : '';
        $this->materialsAmount = $quotation?->materials_amount !== null ? (string) $quotation->materials_amount : '';
        $this->showQuotation = true;
        $this->resetValidation();
    }

    public function saveQuotation(): void
    {
        $this->authorizeTechnician();
        abort_unless($this->tableExists('quotations'), 422, 'Quotations are not available yet.');

        $validated = Validator::make([
            'assessment_notes' => $this->assessmentNotes,
            'labor_amount' => $this->laborAmount,
            'materials_amount' => $this->materialsAmount,
        ], [
            'assessment_notes' => ['required', 'string', 'max:2000'],
            'labor_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'materials_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ])->validate();

        $booking = $this->assignedBookingsQuery()->whereKey($this->quotationBookingId)->firstOrFail();
        abort_unless(in_array($booking->status, ['assigned', 'en_route', 'in_progress'], true), 422, 'Only active jobs can have a quotation.');

        $quotation = Quotation::query()->updateOrCreate(
            ['booking_id' => $booking->id],
            [
                'technician_id' => auth()->id(),
                'assessment_notes' => $validated['assessment_notes'],
                'labor_amount' => $validated['labor_amount'],
                'materials_amount' => $validated['materials_amount'],
                'total_amount' => (float) $validated['labor_amount'] + (float) $validated['materials_amount'],
                'status' => 'awaiting_approval',
                'sent_at' => now(),
                'responded_at' => null,
            ],
        );

        $this->recordAudit('technician.quotation_sent', 'quotation', $quotation->id, [
            'booking_id' => $booking->id,
            'total_amount' => $quotation->total_amount,
        ]);
        $this->closeQuotation();
        $this->successToast('Quotation sent to the customer.');
    }

    public function closeQuotation(): void
    {
        $this->showQuotation = false;
        $this->quotationBookingId = null;
        $this->assessmentNotes = '';
        $this->laborAmount = '';
        $this->materialsAmount = '';
        $this->resetValidation();
    }

    /**
     * @return array{title: string, description: string, label: string, variant: string, operation: string, parameters: array<int, mixed>}
     */
    private function confirmationDefinition(string $action, int $id): array
    {
        $targetId = $id > 0 ? $id : abort(422, 'A booking is required.');

        return match ($action) {
            'accept-request' => [
                'title' => 'Accept this request?',
                'description' => 'This request will be assigned to you and marked as en route.',
                'label' => 'Accept request',
                'variant' => 'primary',
                'operation' => 'acceptRequest',
                'parameters' => [$targetId],
            ],
            'decline-request' => [
                'title' => 'Decline this request?',
                'description' => 'This request will be hidden from your request queue.',
                'label' => 'Decline request',
                'variant' => 'danger',
                'operation' => 'declineRequest',
                'parameters' => [$targetId],
            ],
            'start-job' => [
                'title' => 'Start this repair?',
                'description' => 'The assigned job will be marked as in progress.',
                'label' => 'Start repair',
                'variant' => 'primary',
                'operation' => 'updateBookingStatus',
                'parameters' => [$targetId, 'in_progress'],
            ],
            'start-route' => [
                'title' => 'Start this route?',
                'description' => 'The assigned job will be marked as en route.',
                'label' => 'Start route',
                'variant' => 'primary',
                'operation' => 'updateBookingStatus',
                'parameters' => [$targetId, 'en_route'],
            ],
            'complete-job' => [
                'title' => 'Complete this job?',
                'description' => 'The active job will be closed as completed.',
                'label' => 'Complete job',
                'variant' => 'primary',
                'operation' => 'updateBookingStatus',
                'parameters' => [$targetId, 'completed'],
            ],
            'cancel-job' => [
                'title' => 'Cancel this job?',
                'description' => 'The customer will be notified and the job will be released from your queue.',
                'label' => 'Cancel job',
                'variant' => 'danger',
                'operation' => 'cancelBooking',
                'parameters' => [$targetId],
            ],
            default => abort(422, 'The requested confirmation is not available.'),
        };
    }

    /** @param array{operation: string, parameters: array<int, mixed>, idempotency_key?: string} $pending */
    private function dispatchConfirmedAction(array $pending): void
    {
        $parameters = $pending['parameters'];
        $idempotencyKey = $pending['idempotency_key'] ?? null;

        match ($pending['operation']) {
            'acceptRequest' => $this->acceptRequest((int) $parameters[0], $idempotencyKey),
            'declineRequest' => $this->declineRequest((int) $parameters[0]),
            'updateBookingStatus' => $this->updateBookingStatus((int) $parameters[0], (string) $parameters[1], $idempotencyKey),
            'cancelBooking' => $this->cancelBooking((int) $parameters[0], $idempotencyKey),
            default => abort(422, 'The requested confirmation is not available.'),
        };
    }

    private function notifyActionFailure(Throwable $exception): void
    {
        $message = $exception instanceof HttpExceptionInterface ? trim($exception->getMessage()) : '';

        if (! $exception instanceof HttpExceptionInterface) {
            report($exception);
        }

        Flux::toast(
            variant: 'danger',
            text: $message !== '' ? $message : 'Action failed. Please try again.',
        );
    }

    private function successToast(string $message): void
    {
        Flux::toast(variant: 'success', text: $message);
    }

    private function idempotencyKey(string $key): string
    {
        $key = trim($key);
        abort_unless(Str::isUuid($key), 422, 'The request could not be verified. Please try again.');

        return $key;
    }

    /** @return array{label: string, color: string} */
    private function moduleState(): array
    {
        $online = (string) (auth()->user()->availability_status ?? 'offline') === 'available';

        return match ($this->moduleSlug) {
            'overview' => ['label' => $online ? 'Available for jobs' : 'Offline', 'color' => $online ? 'emerald' : 'zinc'],
            'job-requests' => ['label' => 'Incoming work', 'color' => 'sky'],
            'my-jobs' => ['label' => 'Assigned work', 'color' => 'violet'],
            'schedule' => ['label' => 'Upcoming visits', 'color' => 'amber'],
            'dispatch-routes' => ['label' => 'Live routes', 'color' => 'blue'],
            'earnings' => ['label' => 'Payout overview', 'color' => 'emerald'],
            'ratings-reviews' => ['label' => 'Service quality', 'color' => 'violet'],
            'verification-profile' => ['label' => 'Account status', 'color' => 'teal'],
            'notifications' => ['label' => 'Latest updates', 'color' => 'sky'],
            'support' => ['label' => 'Help center', 'color' => 'amber'],
            default => abort(404, 'Module not found.'),
        };
    }

    /** @return array<string, mixed> */
    private function contentFor(string $module): array
    {
        return match ($module) {
            'overview' => $this->dashboardContent(),
            'job-requests' => $this->requestsContent(),
            'my-jobs' => $this->jobsContent(),
            'schedule' => $this->scheduleContent(),
            'dispatch-routes' => $this->dispatchContent(),
            'earnings' => $this->earningsContent(),
            'ratings-reviews' => $this->reviewsContent(),
            'verification-profile' => $this->profileContent(),
            'notifications' => $this->notificationsContent(),
            'support' => $this->supportContent(),
            default => abort(404, 'Module not found.'),
        };
    }

    /** @return array<string, mixed> */
    private function dashboardContent(): array
    {
        $bookings = $this->assignedBookingsQuery()->latest('bookings.created_at')->limit(50)->get();
        $incomingRequestsQuery = $this->availableRequestsQuery();
        $completed = $bookings->where('status', 'completed');
        $closed = $bookings->whereIn('status', ['completed', 'cancelled', 'no_show'])->count();
        $profile = $this->technicianProfile();
        $activeJob = $bookings->first(fn (object $booking): bool => $booking->status === 'in_progress')
            ?? $bookings->first(fn (object $booking): bool => $booking->status === 'en_route')
            ?? $bookings->first(fn (object $booking): bool => $booking->status === 'assigned');

        return [
            'stats' => [
                ['label' => "Today's jobs", 'value' => Number::format($bookings->filter(fn (object $booking): bool => Carbon::parse($booking->scheduled_at ?? $booking->created_at)->isToday())->count()), 'icon' => 'calendar-days'],
                ['label' => 'Active jobs', 'value' => Number::format($bookings->whereIn('status', ['assigned', 'en_route', 'in_progress'])->count()), 'icon' => 'wrench-screwdriver'],
                ['label' => 'Completed jobs', 'value' => Number::format($completed->count()), 'icon' => 'check-circle'],
                ['label' => 'Average rating', 'value' => $this->averageRating(), 'icon' => 'star'],
            ],
            'activeJob' => $activeJob,
            'verification' => $profile,
            'incomingRequestCount' => (clone $incomingRequestsQuery)->count(),
            'incomingRequests' => (clone $incomingRequestsQuery)->latest('created_at')->limit(5)->get(),
            'upcomingJobs' => $bookings->filter(fn (object $booking): bool => $booking->scheduled_at !== null && in_array($booking->status, ['assigned', 'en_route', 'in_progress'], true))->sortBy('scheduled_at')->take(3),
            'recentJobs' => $bookings->take(6),
            'performance' => [
                ['label' => 'Customer rating', 'value' => $this->averageRating()],
                ['label' => 'Completion rate', 'value' => Number::format($closed > 0 ? $completed->count() / $closed * 100 : 0, 1).'%'],
            ],
            'jobNotes' => $activeJob?->internal_notes,
        ];
    }

    /** @return array<string, mixed> */
    private function requestsContent(): array
    {
        $query = $this->availableRequestsQuery();

        if ($this->requestSearch !== '') {
            $search = '%'.trim($this->requestSearch).'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('reference', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('service_type', 'like', $search)
                    ->orWhere('address', 'like', $search);
            });
        }

        return [
            'stats' => [
                ['label' => 'Available requests', 'value' => Number::format((clone $query)->count()), 'icon' => 'queue-list'],
                ['label' => 'Your availability', 'value' => Str::headline((string) (auth()->user()->availability_status ?? 'offline')), 'icon' => 'bolt'],
            ],
            'requests' => $query->latest('created_at')->paginate(10, ['*'], 'requestsPage'),
        ];
    }

    /** @return array<string, mixed> */
    private function jobsContent(): array
    {
        $query = $this->assignedBookingsQuery();

        if ($this->jobStatus !== 'all') {
            $this->validateValue($this->jobStatus, ['all', 'assigned', 'en_route', 'in_progress', 'completed', 'cancelled', 'no_show']);
            $query->where('status', $this->jobStatus);
        }

        if ($this->jobSearch !== '') {
            $search = '%'.trim($this->jobSearch).'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('reference', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('service_type', 'like', $search)
                    ->orWhere('address', 'like', $search);
            });
        }

        $base = clone $query;

        return [
            'stats' => [
                ['label' => 'Total jobs', 'value' => Number::format((clone $base)->count()), 'icon' => 'clipboard-document-list'],
                ['label' => 'Assigned', 'value' => Number::format((clone $base)->where('status', 'assigned')->count()), 'icon' => 'inbox-arrow-down'],
                ['label' => 'In progress', 'value' => Number::format((clone $base)->where('status', 'in_progress')->count()), 'icon' => 'arrow-path'],
                ['label' => 'Completed', 'value' => Number::format((clone $base)->where('status', 'completed')->count()), 'icon' => 'check-circle'],
            ],
            'jobs' => $query->latest('created_at')->paginate(10, ['*'], 'jobsPage'),
            'quotationsAvailable' => $this->tableExists('quotations'),
        ];
    }

    /** @return array<string, mixed> */
    private function scheduleContent(): array
    {
        $scheduled = $this->assignedBookingsQuery()->whereNotNull('scheduled_at');
        $allScheduled = clone $scheduled;

        if ($this->scheduleRange === 'today') {
            $scheduled->whereDate('scheduled_at', today());
        } elseif ($this->scheduleRange === 'completed') {
            $scheduled->where('status', 'completed');
        } elseif ($this->scheduleRange === 'upcoming') {
            $scheduled->where('scheduled_at', '>=', now())->whereNotIn('status', ['completed', 'cancelled', 'no_show']);
        } else {
            $this->validateValue($this->scheduleRange, ['upcoming', 'today', 'completed', 'all']);
        }

        if ($this->scheduleSearch !== '') {
            $search = '%'.trim($this->scheduleSearch).'%';
            $scheduled->where(function (Builder $builder) use ($search): void {
                $builder->where('reference', 'like', $search)
                    ->orWhere('customer_name', 'like', $search)
                    ->orWhere('service_type', 'like', $search)
                    ->orWhere('address', 'like', $search);
            });
        }

        return [
            'stats' => [
                ['label' => 'Today', 'value' => Number::format((clone $allScheduled)->whereDate('scheduled_at', today())->count()), 'icon' => 'calendar-days'],
                ['label' => 'Upcoming', 'value' => Number::format((clone $allScheduled)->where('scheduled_at', '>=', now())->whereNotIn('status', ['completed', 'cancelled', 'no_show'])->count()), 'icon' => 'clock'],
                ['label' => 'In progress', 'value' => Number::format((clone $allScheduled)->whereIn('status', ['en_route', 'in_progress'])->count()), 'icon' => 'arrow-path'],
                ['label' => 'Completed', 'value' => Number::format((clone $allScheduled)->where('status', 'completed')->count()), 'icon' => 'check-circle'],
            ],
            'schedule' => $scheduled->orderBy('scheduled_at')->paginate(10, ['*'], 'schedulePage'),
        ];
    }

    /** @return array<string, mixed> */
    private function dispatchContent(): array
    {
        $routeJobs = $this->assignedBookingsQuery()->whereIn('status', ['assigned', 'en_route', 'in_progress'])->latest('updated_at')->get();
        $currentRoute = $routeJobs->firstWhere('status', 'in_progress') ?? $routeJobs->first();
        $user = auth()->user();

        return [
            'stats' => [
                ['label' => 'Active routes', 'value' => Number::format($routeJobs->count()), 'icon' => 'map'],
                ['label' => 'Current job', 'value' => $currentRoute instanceof Booking ? (string) $currentRoute->reference : 'None', 'icon' => 'clipboard-document-list'],
                ['label' => 'Location signal', 'value' => $user->latitude !== null && $user->longitude !== null ? 'Live' : 'Waiting', 'icon' => 'map-pin'],
                ['label' => 'Availability', 'value' => Str::headline((string) ($user->availability_status ?? 'offline')), 'icon' => 'bolt'],
            ],
            'currentRoute' => $currentRoute,
            'location' => [
                'latitude' => $user->latitude,
                'longitude' => $user->longitude,
                'last_seen_at' => $user->last_seen_at,
            ],
            'routeJobs' => $routeJobs,
        ];
    }

    /** @return array<string, mixed> */
    private function earningsContent(): array
    {
        $payments = Payment::query()
            ->with(['booking:id,reference,service_type,assigned_technician_id,status', 'booking.service:code,name'])
            ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery
                ->whereBelongsTo(auth()->user(), 'technician')
                ->where('status', 'completed'));
        $total = (clone $payments)->where('payments.status', 'paid')->sum('payments.amount');
        $today = (clone $payments)->where('payments.status', 'paid')->whereDate('payments.paid_at', today())->sum('payments.amount');

        return [
            'stats' => [
                ['label' => 'Total earned', 'value' => '₱'.Number::format((float) $total, 2), 'icon' => 'banknotes'],
                ['label' => 'Earned today', 'value' => '₱'.Number::format((float) $today, 2), 'icon' => 'calendar-days'],
                ['label' => 'Paid jobs', 'value' => Number::format((clone $payments)->where('payments.status', 'paid')->count()), 'icon' => 'check-circle'],
                ['label' => 'Pending payments', 'value' => Number::format((clone $payments)->where('payments.status', 'pending')->count()), 'icon' => 'clock'],
            ],
            'payments' => $payments->latest('payments.created_at')->paginate(10, ['*'], 'earningsPage'),
        ];
    }

    /** @return array<string, mixed> */
    private function reviewsContent(): array
    {
        $reviews = Review::query()
            ->with('booking:id,reference')
            ->whereBelongsTo(auth()->user(), 'technician')
            ->where('status', 'published')
            ->latest('created_at');
        $average = (float) (clone $reviews)->avg('rating');

        return [
            'stats' => [
                ['label' => 'Average rating', 'value' => Number::format($average, 1).' / 5', 'icon' => 'star'],
                ['label' => 'Published reviews', 'value' => Number::format((clone $reviews)->count()), 'icon' => 'chat-bubble-left-right'],
                ['label' => 'Five-star reviews', 'value' => Number::format((clone $reviews)->where('rating', 5)->count()), 'icon' => 'sparkles'],
            ],
            'reviews' => $reviews->paginate(10, ['*'], 'reviewsPage'),
        ];
    }

    /** @return array<string, mixed> */
    private function profileContent(): array
    {
        $profile = $this->technicianProfile();

        return [
            'stats' => [
                ['label' => 'Verification status', 'value' => Str::headline((string) ($profile['status'] ?? 'not_submitted')), 'icon' => 'shield-check'],
                ['label' => 'Service categories', 'value' => Number::format(count($profile['service_categories'] ?? [])), 'icon' => 'wrench-screwdriver'],
                ['label' => 'Years experience', 'value' => Number::format((int) ($profile['years_experience'] ?? 0)), 'icon' => 'briefcase'],
            ],
            'profile' => $profile,
        ];
    }

    /** @return array<string, mixed> */
    private function notificationsContent(): array
    {
        $requests = $this->availableRequestsQuery()->count();
        $activeJobs = $this->assignedBookingsQuery()->whereIn('status', ['assigned', 'en_route', 'in_progress'])->count();
        $profile = $this->technicianProfile();
        $items = collect();

        if ($this->tableExists('notifications')) {
            foreach (auth()->user()->notifications()->latest()->limit(10)->get() as $notification) {
                $data = $notification->data;
                $items->push([
                    'title' => $data['title'] ?? 'Booking update',
                    'detail' => $data['detail'] ?? 'A service booking was updated.',
                    'status' => $data['status'] ?? 'info',
                    'date' => $notification->created_at,
                    'unread' => $notification->read_at === null,
                ]);
            }
        }

        if ($requests > 0) {
            $items->push(['title' => 'New job requests', 'detail' => Number::format($requests).' matching customer requests are waiting for your review.', 'status' => 'attention']);
        }
        if ($activeJobs > 0) {
            $items->push(['title' => 'Active assignments', 'detail' => Number::format($activeJobs).' assigned job(s) are currently in your service flow.', 'status' => 'active']);
        }
        if (($profile['status'] ?? 'not_submitted') !== 'approved') {
            $items->push(['title' => 'Verification update', 'detail' => 'Complete verification so dispatch can send you new assignments.', 'status' => $profile['status'] ?? 'pending']);
        }

        return [
            'stats' => [
                ['label' => 'Updates', 'value' => Number::format($items->count()), 'icon' => 'bell'],
                ['label' => 'Job requests', 'value' => Number::format($requests), 'icon' => 'queue-list'],
                ['label' => 'Active jobs', 'value' => Number::format($activeJobs), 'icon' => 'wrench-screwdriver'],
            ],
            'items' => $items,
        ];
    }

    /** @return array<string, mixed> */
    private function supportContent(): array
    {
        $tickets = SupportTicket::query()
            ->where(function (Builder $query): void {
                $query->where('user_id', auth()->id())->orWhere('assigned_to', auth()->id());
            })
            ->latest('updated_at');

        return [
            'stats' => [
                ['label' => 'Open tickets', 'value' => Number::format((clone $tickets)->whereIn('status', ['open', 'in_progress'])->count()), 'icon' => 'chat-bubble-left-right'],
                ['label' => 'In progress', 'value' => Number::format((clone $tickets)->where('status', 'in_progress')->count()), 'icon' => 'arrow-path'],
                ['label' => 'Resolved', 'value' => Number::format((clone $tickets)->whereIn('status', ['resolved', 'closed'])->count()), 'icon' => 'check-circle'],
            ],
            'tickets' => $tickets->paginate(10, ['*'], 'supportPage'),
        ];
    }

    private function authorizeTechnician(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->isTechnician() && $user->hasActiveAccount(), 403);
    }

    /** @param array<int, string> $allowed */
    private function validateValue(string $value, array $allowed): string
    {
        return Validator::make(['value' => $value], ['value' => ['required', Rule::in($allowed)]])->validate()['value'];
    }

    /** @return Builder<Booking> */
    private function assignedBookingsQuery(): Builder
    {
        return Booking::query()->with(['customer:id,name,avatar_path', 'service:code,name'])->whereBelongsTo(auth()->user(), 'technician');
    }

    private function locationTrackingActive(): bool
    {
        return $this->assignedBookingsQuery()->whereIn('status', ['assigned', 'en_route', 'in_progress'])->exists();
    }

    /** @return Builder<Booking> */
    private function availableRequestsQuery(): Builder
    {
        $query = Booking::query()->with(['customer:id,name,avatar_path', 'service:code,name'])->whereNull('assigned_technician_id')->whereIn('status', ['pending', 'matching']);
        $serviceCategories = $this->approvedServiceCategories();

        if ($serviceCategories === []) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('service_type', ServiceCatalog::matchingCodes($serviceCategories));
        }

        if ($this->tableExists('technician_request_declines')) {
            $query->whereNotIn('id', TechnicianRequestDecline::query()->whereBelongsTo(auth()->user(), 'technician')->select('booking_id'));
        }

        return $query;
    }

    /** @return array<int, string> */
    private function approvedServiceCategories(): array
    {
        if (! $this->tableExists('technician_verifications')) {
            return [];
        }

        $serviceCategories = TechnicianVerification::query()
            ->whereBelongsTo(auth()->user(), 'technician')
            ->where('status', 'approved')
            ->value('service_categories');

        if (is_string($serviceCategories)) {
            $serviceCategories = $this->decodeJson($serviceCategories);
        }

        if (! is_array($serviceCategories)) {
            return [];
        }

        return ServiceCatalog::normalizeCodes($serviceCategories);
    }

    /** @return array<string, mixed>|null */
    private function technicianProfile(): ?array
    {
        if (! $this->tableExists('technician_verifications')) {
            return null;
        }

        $verification = TechnicianVerification::query()
            ->with('documents')
            ->whereBelongsTo(auth()->user(), 'technician')
            ->first();

        if (! $verification) {
            return null;
        }

        $serviceCategories = $verification->getAttribute('service_categories');
        $serviceCategories = is_array($serviceCategories) ? $serviceCategories : $this->decodeJson($serviceCategories);
        $serviceCategories = ServiceCatalog::normalizeCodes($serviceCategories);
        $serviceLabels = ServiceCatalog::activeCatalog()
            ->keyBy('code')
            ->only($serviceCategories)
            ->map(static fn (ServiceCatalog $service): string => (string) $service->name)
            ->values()
            ->all();

        return [
            'id' => $verification->id,
            'status' => (string) $verification->status,
            'risk_level' => (string) $verification->risk_level,
            'service_categories' => $serviceCategories,
            'service_labels' => $serviceLabels,
            'years_experience' => (int) $verification->years_experience,
            'service_area' => $verification->service_area,
            'phone' => $verification->phone,
            'address' => $verification->address,
            'decision_reason' => $verification->decision_reason,
            'submitted_at' => $verification->submitted_at,
            'reviewed_at' => $verification->reviewed_at,
            'documents' => $this->tableExists('technician_documents') ? $verification->documents : collect(),
        ];
    }

    private function averageRating(): string
    {
        $rating = (float) Review::query()->whereBelongsTo(auth()->user(), 'technician')->where('status', 'published')->avg('rating');

        return Number::format($rating, 1).' / 5';
    }

    /** @return array<int, string> */
    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    /** @param array<string, mixed> $details */
    private function recordAudit(string $action, string $targetType, ?int $targetId = null, array $details = []): void
    {
        if (! $this->tableExists('audit_logs')) {
            return;
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => $details,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    private function tableExists(string $table): bool
    {
        return $this->tableAvailability[$table] ??= Schema::hasTable($table);
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->columnAvailability[$table.'.'.$column] ??= Schema::hasColumn($table, $column);
    }
}
