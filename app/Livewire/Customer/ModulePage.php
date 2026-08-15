<?php

namespace App\Livewire\Customer;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Review;
use App\Models\ServiceCatalog;
use App\Models\SupportTicket;
use App\Models\WalkInEntry;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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

#[Layout('layouts.app')]
#[Title('Customer Dashboard')]
class ModulePage extends Component
{
    use WithPagination;

    public string $moduleSlug = 'overview';

    public string $bookingType = 'quick';

    public string $serviceType = '';

    public string $customerName = '';

    public string $customerPhone = '';

    public string $address = '';

    public string $scheduledAt = '';

    public string $description = '';

    public string $walkInServiceType = '';

    public string $walkInPhone = '';

    public string $walkInNotes = '';

    public string $bookingSearch = '';

    public string $bookingStatus = 'all';

    #[Locked]
    public ?int $selectedBookingId = null;

    public bool $showBookingDetails = false;

    #[Locked]
    public ?int $reviewBookingId = null;

    public int $reviewRating = 0;

    public string $reviewComment = '';

    public string $supportSubject = '';

    public string $supportCategory = 'booking';

    public string $supportPriority = 'normal';

    public string $supportMessage = '';

    /** @var array<string, bool> */
    private array $tableAvailability = [];

    /**
     * @return array<string, array{label: string, description: string, icon: string, group: string}>
     */
    public static function modules(): array
    {
        return [
            'overview' => [
                'label' => 'Dashboard',
                'description' => 'See your active service, booking history, and next steps.',
                'icon' => 'home',
                'group' => 'Home',
            ],
            'book-service' => [
                'label' => 'Book a Service',
                'description' => 'Request a background-checked professional for your home.',
                'icon' => 'plus-circle',
                'group' => 'Bookings',
            ],
            'my-bookings' => [
                'label' => 'My Bookings',
                'description' => 'Track current requests and review your service history.',
                'icon' => 'clipboard-document-list',
                'group' => 'Bookings',
            ],
            'walk-in-queue' => [
                'label' => 'Walk-in Queue',
                'description' => 'Join the service queue and track your place in line.',
                'icon' => 'queue-list',
                'group' => 'Bookings',
            ],
            'addresses' => [
                'label' => 'Addresses',
                'description' => 'Review the service locations used in your bookings.',
                'icon' => 'map-pin',
                'group' => 'Account',
            ],
            'quotations' => [
                'label' => 'Quotations',
                'description' => 'Review technician assessments and quotations when available.',
                'icon' => 'document-text',
                'group' => 'Service Journey',
            ],
            'payments' => [
                'label' => 'Payments',
                'description' => 'Review payment records connected to your completed services.',
                'icon' => 'credit-card',
                'group' => 'Service Journey',
            ],
            'ratings-reviews' => [
                'label' => 'Ratings & Reviews',
                'description' => 'Share feedback and keep track of your service ratings.',
                'icon' => 'star',
                'group' => 'Account',
            ],
            'notifications' => [
                'label' => 'Notifications',
                'description' => 'Review booking, payment, and support updates in one place.',
                'icon' => 'bell',
                'group' => 'Account',
            ],
            'support' => [
                'label' => 'Support & Help',
                'description' => 'Ask for help with a booking, payment, or account issue.',
                'icon' => 'chat-bubble-left-right',
                'group' => 'Account',
            ],
            'settings' => [
                'label' => 'Settings',
                'description' => 'Manage your profile, security, and appearance preferences.',
                'icon' => 'cog',
                'group' => 'Account',
            ],
        ];
    }

    public function mount(?string $module = null): void
    {
        $this->authorizeCustomer();
        $this->moduleSlug = $module ?? 'overview';
        abort_unless(array_key_exists($this->moduleSlug, self::modules()), 404);
        $this->customerName = (string) auth()->user()->name;
    }

    public function render(): View
    {
        return view('livewire.customer.module-page', [
            'module' => self::modules()[$this->moduleSlug],
            'moduleState' => $this->moduleState(),
            'content' => $this->contentFor($this->moduleSlug),
            'serviceCatalog' => $this->serviceCatalog(),
            'selectedBooking' => $this->selectedBooking(),
        ]);
    }

    public function updatingBookingSearch(): void
    {
        $this->resetPage('bookingsPage');
    }

    public function updatingBookingStatus(): void
    {
        $this->resetPage('bookingsPage');
    }

    public function startBooking(string $serviceType = ''): void
    {
        $this->authorizeCustomer();

        if ($serviceType !== '') {
            $this->serviceType = $this->validateValue($serviceType, $this->serviceTypes());
        }

        $this->moduleSlug = 'book-service';
        $this->resetValidation();
    }

    public function createBooking(): void
    {
        $this->authorizeCustomer();

        $validated = Validator::make([
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'service_type' => $this->serviceType,
            'booking_type' => $this->bookingType,
            'address' => $this->address,
            'description' => $this->description,
            'scheduled_at' => $this->scheduledAt !== '' ? $this->scheduledAt : null,
        ], [
            'customer_name' => ['required', 'string', 'max:120'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'service_type' => ['required', Rule::in($this->serviceTypes())],
            'booking_type' => ['required', Rule::in(['quick', 'scheduled'])],
            'address' => ['required', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'required_if:booking_type,scheduled', 'date', 'after:now'],
        ])->validate();

        abort_if($this->setting('maintenance_mode') === 'true', 503, 'Bookings are temporarily unavailable during maintenance.');
        abort_if($validated['booking_type'] === 'quick' && $this->setting('quick_booking_enabled') === 'false', 422, 'Quick booking is currently unavailable.');

        $bookingId = DB::transaction(function () use ($validated): int {
            $booking = Booking::create([
                'user_id' => auth()->id(),
                'assigned_technician_id' => null,
                'reference' => $this->bookingReference(),
                'customer_name' => $validated['customer_name'],
                'customer_phone' => $validated['customer_phone'],
                'service_type' => $validated['service_type'],
                'booking_type' => $validated['booking_type'],
                'address' => $validated['address'],
                'description' => $validated['description'],
                'scheduled_at' => $validated['scheduled_at'],
                'status' => $this->setting('booking_auto_match') === 'false' ? 'pending' : 'matching',
                'is_priority' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->recordAudit('customer.booking_created', 'booking', $booking->id, [
                'booking_type' => $validated['booking_type'],
                'service_type' => $validated['service_type'],
            ]);

            return $booking->id;
        });

        $this->resetBookingForm();
        $this->moduleSlug = 'my-bookings';
        $this->successToast('Booking created successfully.');
    }

    public function openBooking(int $bookingId): void
    {
        $this->authorizeCustomer();
        abort_unless($this->customerBookingsQuery()->where('id', $bookingId)->exists(), 404);

        $this->selectedBookingId = $bookingId;
        $this->showBookingDetails = true;
    }

    public function joinWalkInQueue(): void
    {
        $this->authorizeCustomer();
        abort_unless($this->tableExists('walk_in_entries') && $this->columnExists('walk_in_entries', 'user_id'), 422, 'Walk-in queue is not available yet.');
        abort_if($this->setting('maintenance_mode') === 'true', 503, 'Walk-in queue is temporarily unavailable during maintenance.');
        abort_if($this->setting('walk_in_queue_enabled') === 'false', 422, 'Walk-in queue is currently unavailable.');

        $validated = Validator::make([
            'service_type' => $this->walkInServiceType,
            'phone' => $this->walkInPhone,
            'notes' => $this->walkInNotes,
        ], [
            'service_type' => ['required', Rule::in($this->serviceTypes())],
            'phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $entry = WalkInEntry::create([
            'user_id' => auth()->id(),
            'queue_number' => $this->walkInQueueNumber(),
            'customer_name' => auth()->user()->name,
            'customer_phone' => $validated['phone'],
            'service_type' => $validated['service_type'],
            'priority' => 'standard',
            'status' => 'waiting',
            'counter_id' => null,
            'notes' => $validated['notes'],
            'checked_in_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordAudit('customer.walk_in_created', 'walk_in_entry', $entry->id, ['service_type' => $validated['service_type']]);
        $this->walkInServiceType = '';
        $this->walkInPhone = '';
        $this->walkInNotes = '';
        $this->successToast('You joined the walk-in queue.');
    }

    public function closeBooking(): void
    {
        $this->selectedBookingId = null;
        $this->showBookingDetails = false;
    }

    public function startReview(int $bookingId): void
    {
        $this->authorizeCustomer();
        abort_unless($this->tableExists('reviews'), 422, 'Reviews are not available yet.');

        $booking = $this->customerBookingsQuery()->where('id', $bookingId)->first();
        abort_unless($booking && $booking->status === 'completed', 422, 'Only completed bookings can be reviewed.');
        abort_if(Review::query()->where('booking_id', $bookingId)->exists(), 409, 'This booking already has a review.');

        $this->moduleSlug = 'ratings-reviews';
        $this->reviewBookingId = $bookingId;
        $this->reviewRating = 0;
        $this->reviewComment = '';
        $this->resetValidation();
    }

    public function createReview(): void
    {
        $this->authorizeCustomer();
        abort_unless($this->reviewBookingId !== null && $this->tableExists('reviews'), 422, 'A completed booking is required.');

        $validated = Validator::make([
            'rating' => $this->reviewRating,
            'comment' => $this->reviewComment,
        ], [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ])->validate();

        $booking = $this->customerBookingsQuery()->where('id', $this->reviewBookingId)->first();
        abort_unless($booking && $booking->status === 'completed', 422, 'Only completed bookings can be reviewed.');
        abort_if(Review::query()->where('booking_id', $booking->id)->exists(), 409, 'This booking already has a review.');

        Review::create([
            'booking_id' => $booking->id,
            'customer_id' => auth()->id(),
            'technician_id' => $booking->assigned_technician_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'status' => $this->setting('review_moderation') === 'true' ? 'pending' : 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordAudit('customer.review_created', 'review', (int) $booking->id);
        $this->reviewBookingId = null;
        $this->reviewRating = 0;
        $this->reviewComment = '';
        $this->successToast('Review submitted successfully.');
    }

    public function createSupportTicket(): void
    {
        $this->authorizeCustomer();
        abort_unless($this->tableExists('support_tickets'), 422, 'Support tickets are not available yet.');

        $validated = Validator::make([
            'subject' => $this->supportSubject,
            'category' => $this->supportCategory,
            'priority' => $this->supportPriority,
            'message' => $this->supportMessage,
        ], [
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::in(['booking', 'payment', 'technical', 'account', 'other'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high'])],
            'message' => ['required', 'string', 'max:5000'],
        ])->validate();

        $ticket = SupportTicket::create([
            'reference' => $this->supportReference(),
            'user_id' => auth()->id(),
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'assigned_to' => null,
            'latest_message' => $validated['message'],
            'last_response_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->recordAudit('customer.support_ticket_created', 'support_ticket', $ticket->id);
        $this->supportSubject = '';
        $this->supportMessage = '';
        $this->successToast('Support request submitted.');
    }

    /** @return array{label: string, color: string} */
    private function moduleState(): array
    {
        return match ($this->moduleSlug) {
            'overview' => ['label' => 'Live overview', 'color' => 'emerald'],
            'book-service' => ['label' => 'Ready to book', 'color' => 'sky'],
            'my-bookings' => ['label' => 'Service history', 'color' => 'violet'],
            'walk-in-queue' => ['label' => 'Queue status', 'color' => 'amber'],
            'addresses' => ['label' => 'Saved locations', 'color' => 'zinc'],
            'quotations' => ['label' => 'Assessment stage', 'color' => 'amber'],
            'payments' => ['label' => 'Payment history', 'color' => 'emerald'],
            'ratings-reviews' => ['label' => 'Your feedback', 'color' => 'violet'],
            'notifications' => ['label' => 'Latest updates', 'color' => 'sky'],
            'support' => ['label' => 'Help center', 'color' => 'amber'],
            'settings' => ['label' => 'Account preferences', 'color' => 'zinc'],
        };
    }

    /** @return array<string, mixed> */
    private function contentFor(string $module): array
    {
        return match ($module) {
            'overview' => $this->dashboardContent(),
            'book-service' => $this->bookingFormContent(),
            'my-bookings' => $this->bookingsContent(),
            'walk-in-queue' => $this->walkInContent(),
            'addresses' => $this->addressesContent(),
            'quotations' => $this->quotationsContent(),
            'payments' => $this->paymentsContent(),
            'ratings-reviews' => $this->reviewsContent(),
            'notifications' => $this->notificationsContent(),
            'support' => $this->supportContent(),
            'settings' => $this->settingsContent(),
        };
    }

    /** @return array<string, mixed> */
    private function dashboardContent(): array
    {
        $bookings = $this->customerBookingsWithTechnicianQuery()->latest('bookings.created_at')->limit(8)->get();
        $activeBooking = $this->customerBookingsWithTechnicianQuery()->whereIn('bookings.status', ['pending', 'matching', 'assigned', 'en_route', 'in_progress'])->latest('bookings.created_at')->first();
        $base = $this->customerBookingsQuery();
        $completed = (clone $base)->where('status', 'completed')->count();
        $active = (clone $base)->whereIn('status', ['pending', 'matching', 'assigned', 'en_route', 'in_progress'])->count();
        $openSupport = $this->tableExists('support_tickets')
            ? SupportTicket::query()->whereBelongsTo(auth()->user(), 'requester')->whereIn('status', ['open', 'in_progress'])->count()
            : 0;

        return [
            'stats' => [
                ['label' => 'Ongoing booking', 'value' => Number::format($active), 'icon' => 'clipboard-document-list'],
                ['label' => 'Completed jobs', 'value' => Number::format($completed), 'icon' => 'check-circle'],
                ['label' => 'Total bookings', 'value' => Number::format((clone $base)->count()), 'icon' => 'clipboard-document-list'],
                ['label' => 'Open support', 'value' => Number::format($openSupport), 'icon' => 'chat-bubble-left-right'],
            ],
            'activeBooking' => $activeBooking,
            'recentBookings' => $bookings,
            'upcomingBookings' => $this->customerBookingsWithTechnicianQuery()->whereNotNull('bookings.scheduled_at')->where('bookings.scheduled_at', '>=', now())->whereNotIn('bookings.status', ['completed', 'cancelled'])->orderBy('bookings.scheduled_at')->limit(3)->get(),
            'popularServices' => $this->serviceCatalog(),
            'openSupport' => $openSupport,
        ];
    }

    /** @return array<string, mixed> */
    private function bookingFormContent(): array
    {
        return [
            'services' => $this->serviceCatalog(),
            'stats' => [
                ['label' => 'Quick booking', 'value' => $this->setting('quick_booking_enabled') === 'false' ? 'Off' : 'On', 'icon' => 'bolt'],
                ['label' => 'Service types', 'value' => Number::format(count($this->serviceTypes())), 'icon' => 'wrench-screwdriver'],
                ['label' => 'Matching', 'value' => $this->setting('booking_auto_match') === 'false' ? 'Manual' : 'Automatic', 'icon' => 'arrow-path'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function bookingsContent(): array
    {
        $query = $this->customerBookingsWithTechnicianQuery();
        $base = clone $query;

        if ($this->bookingStatus !== 'all') {
            $this->validateValue($this->bookingStatus, ['all', 'pending', 'matching', 'assigned', 'en_route', 'in_progress', 'completed', 'cancelled']);
            $query->where('bookings.status', $this->bookingStatus);
        }

        if ($this->bookingSearch !== '') {
            $search = '%'.trim($this->bookingSearch).'%';
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('bookings.reference', 'like', $search)
                    ->orWhere('bookings.service_type', 'like', $search)
                    ->orWhere('bookings.address', 'like', $search);
            });
        }

        return [
            'stats' => [
                ['label' => 'Total bookings', 'value' => Number::format((clone $base)->count()), 'icon' => 'clipboard-document-list'],
                ['label' => 'In progress', 'value' => Number::format((clone $base)->whereIn('bookings.status', ['pending', 'matching', 'assigned', 'en_route', 'in_progress'])->count()), 'icon' => 'arrow-path'],
                ['label' => 'Completed', 'value' => Number::format((clone $base)->where('bookings.status', 'completed')->count()), 'icon' => 'check-circle'],
                ['label' => 'Cancelled', 'value' => Number::format((clone $base)->where('bookings.status', 'cancelled')->count()), 'icon' => 'x-circle'],
            ],
            'bookings' => $query->latest('bookings.created_at')->paginate(10, ['*'], 'bookingsPage'),
        ];
    }

    /** @return array<string, mixed> */
    private function addressesContent(): array
    {
        $addresses = $this->customerBookingsQuery()
            ->select('address')
            ->selectRaw('COUNT(*) as bookings_count')
            ->selectRaw('MAX(created_at) as last_used_at')
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->groupBy('address')
            ->orderByDesc('last_used_at')
            ->paginate(10, ['*'], 'addressesPage');

        return [
            'stats' => [
                ['label' => 'Service locations', 'value' => Number::format($addresses->total()), 'icon' => 'map-pin'],
                ['label' => 'Most recent', 'value' => $addresses->first()?->last_used_at ? $this->formatDate($addresses->first()->last_used_at) : '—', 'icon' => 'clock'],
            ],
            'addresses' => $addresses,
        ];
    }

    /** @return array<string, mixed> */
    private function walkInContent(): array
    {
        if (! $this->tableExists('walk_in_entries') || ! $this->columnExists('walk_in_entries', 'user_id')) {
            return [
                'stats' => [
                    ['label' => 'Waiting', 'value' => '0', 'icon' => 'clock'],
                    ['label' => 'Being served', 'value' => '0', 'icon' => 'arrow-path'],
                    ['label' => 'Completed', 'value' => '0', 'icon' => 'check-circle'],
                ],
                'entries' => $this->emptyPagination('walkInPage'),
                'services' => $this->serviceCatalog(),
            ];
        }

        $query = WalkInEntry::query()->whereBelongsTo(auth()->user(), 'customer');

        return [
            'stats' => [
                ['label' => 'Waiting', 'value' => Number::format((clone $query)->where('status', 'waiting')->count()), 'icon' => 'clock'],
                ['label' => 'Being served', 'value' => Number::format((clone $query)->whereIn('status', ['called', 'serving'])->count()), 'icon' => 'arrow-path'],
                ['label' => 'Completed', 'value' => Number::format((clone $query)->where('status', 'completed')->count()), 'icon' => 'check-circle'],
            ],
            'entries' => $query->latest('checked_in_at')->paginate(10, ['*'], 'walkInPage'),
            'services' => $this->serviceCatalog(),
        ];
    }

    /** @return array<string, mixed> */
    private function quotationsContent(): array
    {
        return [
            'stats' => [
                ['label' => 'Awaiting assessment', 'value' => '0', 'icon' => 'clipboard-document-list'],
                ['label' => 'Pending approval', 'value' => '0', 'icon' => 'clock'],
                ['label' => 'Approved', 'value' => '0', 'icon' => 'check-circle'],
            ],
            'available' => false,
            'message' => 'Quotations appear here after a technician assessment. No quotation records are stored in this version yet.',
        ];
    }

    /** @return array<string, mixed> */
    private function paymentsContent(): array
    {
        if (! $this->tableExists('payments')) {
            return ['stats' => $this->paymentStats(), 'payments' => $this->emptyPagination('paymentsPage')];
        }

        $query = Payment::query()
            ->with(['booking:id,reference,service_type,status,user_id'])
            ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->whereBelongsTo(auth()->user(), 'customer'));

        return [
            'stats' => [
                ['label' => 'Total paid', 'value' => '₱'.Number::format((float) (clone $query)->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->where('status', 'completed'))->where('payments.status', 'paid')->sum('payments.amount'), 2), 'icon' => 'banknotes'],
                ['label' => 'Paid records', 'value' => Number::format((clone $query)->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->where('status', 'completed'))->where('payments.status', 'paid')->count()), 'icon' => 'check-circle'],
                ['label' => 'Pending', 'value' => Number::format((clone $query)->where('payments.status', 'pending')->count()), 'icon' => 'clock'],
            ],
            'payments' => $query->latest('payments.created_at')->paginate(10, ['*'], 'paymentsPage'),
        ];
    }

    /** @return array<string, mixed> */
    private function reviewsContent(): array
    {
        $reviews = $this->tableExists('reviews')
            ? Review::query()->with(['booking:id,reference,service_type', 'technician:id,name'])->whereBelongsTo(auth()->user(), 'customer')->latest('created_at')->paginate(10, ['*'], 'reviewsPage')
            : $this->emptyPagination('reviewsPage');
        $completed = $this->customerBookingsQuery()->where('status', 'completed');

        if ($this->tableExists('reviews')) {
            $completed->whereDoesntHave('review');
        }

        return [
            'stats' => [
                ['label' => 'Average rating', 'value' => Number::format((float) ($reviews->total() > 0 ? Review::query()->whereBelongsTo(auth()->user(), 'customer')->avg('rating') : 0), 1).' / 5', 'icon' => 'star'],
                ['label' => 'Reviews submitted', 'value' => Number::format($reviews->total()), 'icon' => 'chat-bubble-left-right'],
                ['label' => 'Ready to review', 'value' => Number::format((clone $completed)->count()), 'icon' => 'sparkles'],
            ],
            'reviews' => $reviews,
            'reviewableBookings' => $completed->latest('created_at')->limit(6)->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function notificationsContent(): array
    {
        $items = collect();
        $bookings = $this->customerBookingsWithTechnicianQuery()->latest('bookings.updated_at')->limit(6)->get();

        foreach ($bookings as $booking) {
            $items->push([
                'title' => $this->serviceLabel($booking->service_type).' booking '.$this->statusLabel($booking->status),
                'detail' => $booking->reference.' · '.$booking->address,
                'status' => $booking->status,
                'date' => $booking->updated_at ?? $booking->created_at,
            ]);
        }

        if ($this->tableExists('support_tickets')) {
            foreach (SupportTicket::query()->whereBelongsTo(auth()->user(), 'requester')->latest('updated_at')->limit(3)->get() as $ticket) {
                $items->push([
                    'title' => 'Support request '.$this->statusLabel($ticket->status),
                    'detail' => $ticket->reference.' · '.$ticket->subject,
                    'status' => $ticket->status,
                    'date' => $ticket->updated_at,
                ]);
            }
        }

        return [
            'stats' => [
                ['label' => 'Updates', 'value' => Number::format($items->count()), 'icon' => 'bell'],
                ['label' => 'Active bookings', 'value' => Number::format($bookings->whereIn('status', ['pending', 'matching', 'assigned', 'en_route', 'in_progress'])->count()), 'icon' => 'arrow-path'],
                ['label' => 'Open support', 'value' => Number::format($items->where('status', 'open')->count()), 'icon' => 'chat-bubble-left-right'],
            ],
            'items' => $items->sortByDesc('date')->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function supportContent(): array
    {
        $tickets = $this->tableExists('support_tickets')
            ? SupportTicket::query()->whereBelongsTo(auth()->user(), 'requester')->latest('updated_at')->paginate(10, ['*'], 'ticketsPage')
            : $this->emptyPagination('ticketsPage');

        return [
            'stats' => [
                ['label' => 'Open', 'value' => Number::format((clone $tickets->getCollection())->where('status', 'open')->count()), 'icon' => 'chat-bubble-left-right'],
                ['label' => 'In progress', 'value' => Number::format((clone $tickets->getCollection())->where('status', 'in_progress')->count()), 'icon' => 'arrow-path'],
                ['label' => 'Resolved', 'value' => Number::format((clone $tickets->getCollection())->where('status', 'resolved')->count()), 'icon' => 'check-circle'],
            ],
            'tickets' => $tickets,
        ];
    }

    /** @return array<string, mixed> */
    private function settingsContent(): array
    {
        $user = auth()->user();

        return [
            'stats' => [
                ['label' => 'Account status', 'value' => Str::headline((string) ($user->account_status ?? 'active')), 'icon' => 'check-circle'],
                ['label' => 'Email verification', 'value' => $user->email_verified_at ? 'Verified' : 'Pending', 'icon' => 'shield-check'],
            ],
            'user' => $user,
        ];
    }

    private function authorizeCustomer(): void
    {
        abort_unless(auth()->user()?->isCustomer(), 403);
    }

    private function customerBookingsQuery(): Builder
    {
        return Booking::query()->whereBelongsTo(auth()->user(), 'customer');
    }

    private function customerBookingsWithTechnicianQuery(): Builder
    {
        return Booking::query()
            ->select(['id', 'user_id', 'assigned_technician_id', 'reference', 'service_type', 'booking_type', 'status', 'address', 'description', 'scheduled_at', 'created_at', 'updated_at'])
            ->with('technician:id,name')
            ->whereBelongsTo(auth()->user(), 'customer');
    }

    private function selectedBooking(): ?object
    {
        if ($this->selectedBookingId === null) {
            return null;
        }

        return $this->customerBookingsQuery()->where('id', $this->selectedBookingId)->first();
    }

    /** @return array<int, string> */
    private function serviceTypes(): array
    {
        return $this->serviceCatalog()->pluck('code')->map(static fn (mixed $code): string => (string) $code)->all();
    }

    /** @return Collection<int, object> */
    private function serviceCatalog(): Collection
    {
        if ($this->tableExists('service_catalog')) {
            return once(fn (): Collection => ServiceCatalog::query()->where('is_active', true)->orderBy('name')->get(['code', 'name', 'category', 'base_price', 'description']));
        }

        return collect();
    }

    private function bookingReference(): string
    {
        do {
            $reference = 'FX-'.Str::upper(Str::random(8));
        } while (Booking::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function supportReference(): string
    {
        do {
            $reference = 'SUP-'.Str::upper(Str::random(8));
        } while (SupportTicket::query()->where('reference', $reference)->exists());

        return $reference;
    }

    private function walkInQueueNumber(): string
    {
        do {
            $queueNumber = 'W-'.Str::upper(Str::random(6));
        } while (WalkInEntry::query()->where('queue_number', $queueNumber)->exists());

        return $queueNumber;
    }

    private function setting(string $key): string
    {
        if (! $this->tableExists('platform_settings')) {
            return '';
        }

        return (string) (PlatformSetting::query()->where('key', $key)->value('value') ?? '');
    }

    private function statusLabel(?string $status): string
    {
        return Str::headline((string) $status);
    }

    private function serviceLabel(?string $service): string
    {
        return collect($this->serviceCatalog())->firstWhere('code', $service)?->name ?? Str::headline((string) $service);
    }

    private function formatDate(mixed $date): string
    {
        return $date ? Carbon::parse($date)->format('M d, Y') : '—';
    }

    private function formatDateTime(mixed $date): string
    {
        return $date ? Carbon::parse($date)->format('M d, Y · g:i A') : '—';
    }

    /** @return array<int, array{label: string, value: string, icon: string}> */
    private function paymentStats(): array
    {
        return [
            ['label' => 'Total paid', 'value' => '₱0.00', 'icon' => 'banknotes'],
            ['label' => 'Paid records', 'value' => '0', 'icon' => 'check-circle'],
            ['label' => 'Pending', 'value' => '0', 'icon' => 'clock'],
        ];
    }

    private function emptyPagination(string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 10, LengthAwarePaginator::resolveCurrentPage($pageName), [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    private function resetBookingForm(): void
    {
        $this->bookingType = 'quick';
        $this->serviceType = '';
        $this->customerPhone = '';
        $this->address = '';
        $this->scheduledAt = '';
        $this->description = '';
        $this->resetValidation();
    }

    private function validateValue(string $value, array $allowed): string
    {
        abort_unless(in_array($value, $allowed, true), 422, 'The selected option is not available.');

        return $value;
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

    private function successToast(string $message): void
    {
        Flux::toast(variant: 'success', text: $message);
    }

    private function tableExists(string $table): bool
    {
        return $this->tableAvailability[$table] ??= Schema::hasTable($table);
    }

    private function columnExists(string $table, string $column): bool
    {
        return Schema::hasColumn($table, $column);
    }
}
