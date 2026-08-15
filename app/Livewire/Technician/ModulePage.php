<?php

namespace App\Livewire\Technician;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\SupportTicket;
use App\Models\TechnicianRequestDecline;
use App\Models\TechnicianVerification;
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

    /** @var array{operation: string, parameters: array<int, mixed>}|null */
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

        auth()->user()->update([
            'availability_status' => $status,
        ]);

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

    public function acceptRequest(int $bookingId): void
    {
        $this->authorizeTechnician();

        DB::transaction(function () use ($bookingId): void {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);
            abort_unless($booking, 404);
            abort_if($booking->assigned_technician_id !== null || ! in_array($booking->status, ['pending', 'matching'], true), 409, 'This request is no longer available.');
            abort_unless(
                $this->tableExists('technician_verifications')
                && TechnicianVerification::query()->whereBelongsTo(auth()->user(), 'technician')->where('status', 'approved')->exists(),
                403,
                'Your technician verification must be approved before accepting requests.',
            );
            abort_if(
                $this->columnExists('users', 'account_status')
                && auth()->user()->account_status !== 'active',
                403,
                'Your technician account is not active.',
            );
            abort_if(
                $this->tableExists('technician_request_declines')
                && TechnicianRequestDecline::query()->whereBelongsTo(auth()->user(), 'technician')->whereBelongsTo($booking, 'booking')->exists(),
                409,
                'You already declined this request.',
            );

            $booking->update([
                'assigned_technician_id' => auth()->id(),
                'status' => 'assigned',
            ]);
        });

        $this->recordAudit('technician.request_accepted', 'booking', $bookingId);
        $this->successToast('Job accepted and added to My Jobs.');
    }

    public function declineRequest(int $bookingId): void
    {
        $this->authorizeTechnician();
        $booking = Booking::query()->find($bookingId);
        abort_unless($booking, 404);
        abort_if($booking->assigned_technician_id !== null || ! in_array($booking->status, ['pending', 'matching'], true), 409, 'This request is no longer available.');
        abort_unless($this->tableExists('technician_request_declines'), 422, 'Request decline tracking is not available yet.');

        TechnicianRequestDecline::query()->firstOrCreate([
            'technician_id' => auth()->id(),
            'booking_id' => $bookingId,
        ]);

        $this->recordAudit('technician.request_declined', 'booking', $bookingId);
        $this->successToast('Request removed from your queue.');
    }

    public function updateBookingStatus(int $bookingId, string $status): void
    {
        $this->authorizeTechnician();
        $this->validateValue($status, ['en_route', 'in_progress', 'completed']);
        $paymentId = null;
        $paymentCreated = false;

        DB::transaction(function () use ($bookingId, $status, &$paymentId, &$paymentCreated): void {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);
            abort_unless($booking, 404);
            abort_unless((int) $booking->assigned_technician_id === (int) auth()->id(), 403);
            abort_if($status === 'en_route' && $booking->status !== 'assigned', 422, 'Only assigned jobs can start a route.');
            abort_if($status === 'in_progress' && ! in_array($booking->status, ['assigned', 'en_route'], true), 422, 'Only assigned jobs can be started.');
            abort_if($status === 'completed' && $booking->status !== 'in_progress', 422, 'Only active jobs can be completed.');

            $booking->update([
                'status' => $status,
            ]);

            if ($status === 'completed' && $this->tableExists('payments')) {
                $payment = Booking::query()->findOrFail($bookingId)->ensurePayment();
                $paymentId = (int) $payment->id;
                $paymentCreated = $payment->wasRecentlyCreated;
            }

            if ($status === 'completed' && ! Booking::query()->whereBelongsTo(auth()->user(), 'technician')->whereIn('status', ['assigned', 'en_route', 'in_progress'])->exists()) {
                auth()->user()->update(['availability_status' => 'available']);
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

    /**
     * @return array{title: string, description: string, label: string, variant: string, operation: string, parameters: array<int, mixed>}
     */
    private function confirmationDefinition(string $action, int $id): array
    {
        $targetId = $id > 0 ? $id : abort(422, 'A booking is required.');

        return match ($action) {
            'accept-request' => [
                'title' => 'Accept this request?',
                'description' => 'This request will be assigned to you and moved to My Jobs.',
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
            default => abort(422, 'The requested confirmation is not available.'),
        };
    }

    /** @param array{operation: string, parameters: array<int, mixed>} $pending */
    private function dispatchConfirmedAction(array $pending): void
    {
        $parameters = $pending['parameters'];

        match ($pending['operation']) {
            'acceptRequest' => $this->acceptRequest((int) $parameters[0]),
            'declineRequest' => $this->declineRequest((int) $parameters[0]),
            'updateBookingStatus' => $this->updateBookingStatus((int) $parameters[0], (string) $parameters[1]),
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
        };
    }

    /** @return array<string, mixed> */
    private function dashboardContent(): array
    {
        $bookings = $this->assignedBookingsQuery()->latest('bookings.created_at')->limit(50)->get();
        $incomingRequestsQuery = $this->availableRequestsQuery();
        $completed = $bookings->where('status', 'completed');
        $closed = $bookings->whereIn('status', ['completed', 'cancelled'])->count();
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
            $this->validateValue($this->jobStatus, ['all', 'assigned', 'en_route', 'in_progress', 'completed', 'cancelled']);
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
            $scheduled->where('scheduled_at', '>=', now())->whereNotIn('status', ['completed', 'cancelled']);
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
                ['label' => 'Upcoming', 'value' => Number::format((clone $allScheduled)->where('scheduled_at', '>=', now())->whereNotIn('status', ['completed', 'cancelled'])->count()), 'icon' => 'clock'],
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
                ['label' => 'Current job', 'value' => $currentRoute?->reference ?? 'None', 'icon' => 'clipboard-document-list'],
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
        $items = [];

        if ($requests > 0) {
            $items[] = ['title' => 'New job requests', 'detail' => Number::format($requests).' matching customer requests are waiting for your review.', 'status' => 'attention'];
        }
        if ($activeJobs > 0) {
            $items[] = ['title' => 'Active assignments', 'detail' => Number::format($activeJobs).' assigned job(s) are currently in your service flow.', 'status' => 'active'];
        }
        if (($profile['status'] ?? 'not_submitted') !== 'approved') {
            $items[] = ['title' => 'Verification update', 'detail' => 'Complete verification so dispatch can send you new assignments.', 'status' => $profile['status'] ?? 'pending'];
        }

        return [
            'stats' => [
                ['label' => 'Updates', 'value' => Number::format(count($items)), 'icon' => 'bell'],
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
        abort_unless(auth()->user()?->isTechnician(), 403);
    }

    /** @param array<int, string> $allowed */
    private function validateValue(string $value, array $allowed): string
    {
        return Validator::make(['value' => $value], ['value' => ['required', Rule::in($allowed)]])->validate()['value'];
    }

    private function assignedBookingsQuery(): Builder
    {
        return Booking::query()->with(['customer:id,name', 'service:code,name'])->whereBelongsTo(auth()->user(), 'technician');
    }

    private function availableRequestsQuery(): Builder
    {
        $query = Booking::query()->with(['customer:id,name', 'service:code,name'])->whereNull('assigned_technician_id')->whereIn('status', ['pending', 'matching']);

        if ($this->tableExists('technician_request_declines')) {
            $query->whereNotIn('id', TechnicianRequestDecline::query()->whereBelongsTo(auth()->user(), 'technician')->select('booking_id'));
        }

        return $query;
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

        return [
            'id' => $verification->id,
            'status' => (string) $verification->status,
            'risk_level' => (string) $verification->risk_level,
            'service_categories' => is_array($verification->service_categories) ? $verification->service_categories : $this->decodeJson($verification->service_categories),
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
