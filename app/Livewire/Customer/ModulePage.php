<?php

namespace App\Livewire\Customer;

use App\Concerns\HandlesProfilePhoto;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\Quotation;
use App\Models\Review;
use App\Models\ServiceCatalog;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\WalkInEntry;
use Flux\Flux;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
use Throwable;

#[Layout('layouts.app')]
#[Title('Customer Dashboard')]
class ModulePage extends Component
{
    use HandlesProfilePhoto;
    use WithPagination;

    public string $moduleSlug = 'overview';

    public string $mobileHomeView = 'home';

    public string $mobileNavigateTab = 'recent';

    public string $mobileTechnicianStoreSearch = '';

    public string $bookingType = 'quick';

    public string $serviceType = '';

    public string $serviceCategory = '';

    public string $customerName = '';

    public string $customerPhone = '';

    public string $mobileCountryCode = '+63';

    public string $address = '';

    public ?float $addressLatitude = null;

    public ?float $addressLongitude = null;

    /** @var array<int, array{label: string, latitude: float, longitude: float}> */
    public array $addressSuggestions = [];

    public string $scheduledAt = '';

    public string $description = '';

    public string $walkInServiceType = '';

    public string $walkInPhone = '';

    public string $walkInNotes = '';

    public string $bookingSearch = '';

    public string $bookingStatus = 'all';

    #[Locked]
    public ?int $selectedBookingId = null;

    #[Locked]
    public ?int $selectedPaymentId = null;

    #[Locked]
    public ?int $selectedTechnicianId = null;

    public string $bookingIdempotencyKey = '';

    public bool $showBookingDetails = false;

    public bool $showPaymentDetails = false;

    #[Locked]
    public ?int $cancellationBookingId = null;

    public string $cancellationIdempotencyKey = '';

    public bool $showCancellation = false;

    public string $cancellationReason = '';

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
            'quotations' => [
                'label' => 'Quotations',
                'description' => 'Review technician assessments and quotations when available.',
                'icon' => 'document-text',
                'group' => 'Service Journey',
            ],
            'payments' => [
                'label' => 'Payments',
                'description' => 'Review your payment history and transaction details for completed services.',
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
        $this->customerPhone = $this->profilePhoneNumber((string) (auth()->user()->phone ?? ''));
        $this->bookingIdempotencyKey = Str::uuid()->toString();
    }

    public function render(): View
    {
        return view('livewire.customer.module-page', [
            'module' => self::modules()[$this->moduleSlug],
            'moduleState' => $this->moduleState(),
            'content' => $this->contentFor($this->moduleSlug),
            'serviceCatalog' => $this->serviceCatalog(),
            'technicianStores' => $this->moduleSlug === 'overview' ? $this->technicianStores() : collect(),
            'selectedBooking' => $this->selectedBooking(),
            'selectedPayment' => $this->selectedPayment(),
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

    public function updatedAddress(): void
    {
        $this->resetValidation('address');
    }

    public function startBooking(string $serviceType = ''): void
    {
        $this->authorizeCustomer();

        if ($serviceType !== '') {
            $service = $this->serviceCatalog()->firstWhere('code', $serviceType);
            abort_unless($service instanceof ServiceCatalog, 404);
            $this->serviceCategory = (string) $service->category;
            $this->serviceType = (string) $service->code;
        }

        $this->moduleSlug = 'book-service';
        $this->resetValidation();
    }

    public function openMobileQuickBook(): void
    {
        $this->authorizeCustomer();
        $this->mobileHomeView = 'quick-book';
        $this->bookingType = 'quick';
        $this->serviceCategory = '';
        $this->serviceType = '';
        $this->selectedTechnicianId = null;
        $this->mobileTechnicianStoreSearch = '';
        $this->resetValidation();
    }

    public function openMobileSchedule(): void
    {
        $this->authorizeCustomer();
        $this->mobileHomeView = 'schedule';
        $this->bookingType = 'scheduled';
        $this->serviceCategory = '';
        $this->serviceType = '';
        $this->selectedTechnicianId = null;
        $this->mobileTechnicianStoreSearch = '';
        $this->resetValidation();
    }

    public function chooseServiceCategory(string $category): void
    {
        $this->authorizeCustomer();

        if ($category === '') {
            $this->serviceCategory = '';
            $this->serviceType = '';
            $this->resetValidation(['serviceCategory', 'serviceType']);

            return;
        }

        $this->serviceCategory = $this->validateValue($category, $this->serviceCategories());
        $this->serviceType = '';
        $this->resetValidation(['serviceCategory', 'serviceType']);
    }

    public function chooseServiceType(string $serviceType): void
    {
        $this->authorizeCustomer();
        $service = $this->serviceCatalog()->firstWhere('code', $serviceType);

        abort_unless(
            $service instanceof ServiceCatalog
            && $this->serviceCategory !== ''
            && $service->category === $this->serviceCategory,
            422,
            'Choose a service from the selected category.',
        );

        $this->serviceType = (string) $service->code;
        $this->resetValidation('serviceType');
    }

    public function selectMobileTechnicianStore(int $technicianId): void
    {
        $this->authorizeCustomer();
        abort_unless($this->technicianStores()->firstWhere('id', $technicianId) !== null, 404);

        $this->selectedTechnicianId = $technicianId;
        $this->bookingType = 'scheduled';
        $this->mobileHomeView = 'schedule-form';
        $this->mobileTechnicianStoreSearch = '';
        $this->resetValidation();
    }

    public function openMobileNavigate(): void
    {
        $this->authorizeCustomer();
        $this->mobileHomeView = 'navigate';
        $this->mobileNavigateTab = 'recent';
        $this->addressSuggestions = [];
        $this->resetValidation();
    }

    public function closeMobileHomeView(): void
    {
        $this->authorizeCustomer();
        $this->mobileHomeView = 'home';
        $this->mobileNavigateTab = 'recent';
        $this->selectedTechnicianId = null;
        $this->mobileTechnicianStoreSearch = '';
        $this->addressSuggestions = [];
        $this->resetValidation();
    }

    public function chooseMobileScheduleService(string $serviceType): void
    {
        $this->authorizeCustomer();
        $service = $this->serviceCatalog()->firstWhere('code', $serviceType);
        abort_unless($service instanceof ServiceCatalog, 404);
        $this->serviceCategory = (string) $service->category;
        $this->serviceType = (string) $service->code;
        $this->bookingType = 'scheduled';
        $this->mobileHomeView = 'schedule-form';
        $this->resetValidation();
    }

    public function selectMobileRecentDestination(int $bookingId): void
    {
        $this->authorizeCustomer();
        $booking = $this->customerBookingsWithTechnicianQuery()->whereKey($bookingId)->firstOrFail();

        $this->address = (string) $booking->address;
        $this->addressLatitude = $booking->latitude !== null ? (float) $booking->latitude : null;
        $this->addressLongitude = $booking->longitude !== null ? (float) $booking->longitude : null;
        $this->openMobileQuickBook();
    }

    public function selectMobileAddress(int $index): void
    {
        $this->selectAddress($index);
        $this->openMobileQuickBook();
    }

    public function setMobileNavigateTab(string $tab): void
    {
        $this->authorizeCustomer();
        $this->mobileNavigateTab = $this->validateValue($tab, ['recent', 'suggested', 'saved']);
    }

    public function searchAddress(?string $query = null): void
    {
        $this->authorizeCustomer();

        $isTyping = $query !== null;

        if ($isTyping) {
            $this->address = trim($query);
            $this->resetValidation('address');
        }

        $query = trim($this->address);

        if (Str::length($query) < 3) {
            $this->addressSuggestions = [];

            if (! $isTyping) {
                $this->addError('address', 'Enter at least 3 characters before searching the map.');
            }

            return;
        }

        try {
            $response = Http::acceptJson()
                ->withUserAgent(config('app.name', 'FixTrack'))
                ->connectTimeout(2)
                ->timeout(3)
                ->retry(2, 200, throw: false)
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'countrycodes' => 'ph',
                    'limit' => 5,
                ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->addressSuggestions = [];

            if (! $isTyping) {
                $this->errorToast('The map search is temporarily unavailable.');
            }

            return;
        }

        if (! $response->successful()) {
            Log::warning('Address search provider failed.', [
                'status' => $response->status(),
                'query_hash' => sha1($query),
            ]);
            $this->addressSuggestions = [];

            if (! $isTyping) {
                $this->errorToast('The map search is temporarily unavailable.');
            }

            return;
        }

        $results = $response->json();
        $results = is_array($results) ? $results : [];

        $this->addressSuggestions = collect($results)
            ->map(static function (mixed $result): ?array {
                if (! is_array($result) || ! isset($result['display_name'], $result['lat'], $result['lon'])) {
                    return null;
                }

                return [
                    'label' => (string) $result['display_name'],
                    'latitude' => (float) $result['lat'],
                    'longitude' => (float) $result['lon'],
                ];
            })
            ->filter()
            ->values()
            ->all();

        if ($this->addressSuggestions === [] && ! $isTyping) {
            $this->errorToast('No matching locations were found.');
        }
    }

    public function updateLocationFromCoordinates(float $latitude, float $longitude): void
    {
        $this->authorizeCustomer();

        $validated = Validator::make([
            'latitude' => $latitude,
            'longitude' => $longitude,
        ], [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ])->validate();

        $this->addressLatitude = (float) $validated['latitude'];
        $this->addressLongitude = (float) $validated['longitude'];
        $this->addressSuggestions = [];
        $this->resetValidation('address');

        try {
            $response = Http::acceptJson()
                ->withUserAgent(config('app.name', 'FixTrack'))
                ->connectTimeout(2)
                ->timeout(3)
                ->retry(2, 200, throw: false)
                ->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $this->addressLatitude,
                    'lon' => $this->addressLongitude,
                    'format' => 'jsonv2',
                    'addressdetails' => 1,
                    'zoom' => 18,
                ]);
        } catch (Throwable $exception) {
            report($exception);
            $this->address = '';
            $this->addError('address', 'We found the pin, but could not read the address. Please enter it manually.');

            return;
        }

        if (! $response->successful()) {
            Log::warning('Address reverse geocoder failed.', [
                'status' => $response->status(),
                'location_hash' => sha1($this->addressLatitude.'|'.$this->addressLongitude),
            ]);
            $this->address = '';
            $this->addError('address', 'We found the pin, but could not read the address. Please enter it manually.');

            return;
        }

        $address = trim((string) $response->json('display_name', ''));

        if ($address === '') {
            $this->address = '';
            $this->addError('address', 'We found the pin, but could not read the address. Please enter it manually.');

            return;
        }

        $this->address = $address;
        $this->resetValidation('address');
    }

    public function selectAddress(int $index): void
    {
        $this->authorizeCustomer();
        $suggestion = $this->addressSuggestions[$index] ?? null;
        abort_unless(is_array($suggestion), 404);

        $this->address = $suggestion['label'];
        $this->addressLatitude = $suggestion['latitude'];
        $this->addressLongitude = $suggestion['longitude'];
        $this->addressSuggestions = [];
        $this->resetValidation('address');
    }

    public function createBooking(): void
    {
        $this->authorizeCustomer();
        $isMobileBookingFlow = $this->moduleSlug === 'overview' && in_array($this->mobileHomeView, ['quick-book', 'schedule-form'], true);
        $this->customerName = (string) auth()->user()->name;
        $bookingIdempotencyKey = $this->idempotencyKey($this->bookingIdempotencyKey ?: Str::uuid()->toString());
        $this->bookingIdempotencyKey = $bookingIdempotencyKey;

        $validated = Validator::make([
            'customer_name' => $this->customerName,
            'mobile_country_code' => $this->mobileCountryCode,
            'customer_phone' => $this->normalizePhoneNumber($this->customerPhone, $this->mobileCountryCode),
            'service_type' => $this->serviceType,
            'booking_type' => $this->bookingType,
            'address' => $this->address,
            'address_latitude' => $this->addressLatitude,
            'address_longitude' => $this->addressLongitude,
            'description' => $this->description,
            'scheduled_at' => $this->scheduledAt !== '' ? $this->scheduledAt : null,
        ], [
            'customer_name' => ['required', 'string', 'max:120'],
            'mobile_country_code' => ['required', Rule::in(array_keys($this->countryCallingCodes()))],
            'customer_phone' => ['required', 'string', 'regex:/^\+[1-9][0-9]{7,14}$/'],
            'service_type' => ['required', Rule::in($this->serviceTypes())],
            'booking_type' => ['required', Rule::in(['quick', 'scheduled'])],
            'address' => ['required', 'string', 'max:500'],
            'address_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'address_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'scheduled_at' => ['nullable', 'required_if:booking_type,scheduled', 'date', 'after:now'],
        ])->validate();

        abort_if($this->setting('maintenance_mode') === 'true', 503, 'Bookings are temporarily unavailable during maintenance.');
        abort_if($validated['booking_type'] === 'quick' && $this->setting('quick_booking_enabled') === 'false', 422, 'Quick booking is currently unavailable.');

        $selectedTechnicianId = $validated['booking_type'] === 'scheduled' ? $this->selectedTechnicianId : null;

        if ($selectedTechnicianId !== null) {
            $selectedTechnician = $this->technicianStores()->firstWhere('id', $selectedTechnicianId);
            abort_unless($selectedTechnician !== null, 404);
            abort_unless(
                $selectedTechnician->technicianVerification?->supportsService((string) $validated['service_type']),
                422,
                'The selected technician does not offer this exact service.',
            );
        }

        try {
            $bookingId = DB::transaction(function () use ($validated, $selectedTechnicianId, $bookingIdempotencyKey): int {
                $customer = User::query()->lockForUpdate()->findOrFail(auth()->id());
                $existingBooking = $customer->bookings()->where('idempotency_key', $bookingIdempotencyKey)->first();

                if ($existingBooking !== null) {
                    return $existingBooking->id;
                }

                abort_if(
                    $customer->bookings()->whereIn('status', Booking::ACTIVE_STATUSES)->exists(),
                    409,
                    'You already have an active booking. Complete or cancel it before creating another request.',
                );

                $booking = Booking::create([
                    'user_id' => auth()->id(),
                    'assigned_technician_id' => $selectedTechnicianId,
                    'reference' => $this->bookingReference(),
                    'idempotency_key' => $bookingIdempotencyKey,
                    'customer_name' => $validated['customer_name'],
                    'customer_phone' => $validated['customer_phone'],
                    'service_type' => $validated['service_type'],
                    'booking_type' => $validated['booking_type'],
                    'address' => $validated['address'],
                    'latitude' => $validated['address_latitude'],
                    'longitude' => $validated['address_longitude'],
                    'description' => $validated['description'],
                    'scheduled_at' => $validated['scheduled_at'],
                    'status' => $selectedTechnicianId !== null
                        ? 'assigned'
                        : ($this->setting('booking_auto_match') === 'false' ? 'pending' : 'matching'),
                    'is_priority' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $booking->recordInitialStatus(auth()->user(), 'Booking created.', [
                    'booking_type' => $validated['booking_type'],
                    'service_type' => $validated['service_type'],
                ]);

                $this->recordAudit('customer.booking_created', 'booking', $booking->id, [
                    'booking_type' => $validated['booking_type'],
                    'service_type' => $validated['service_type'],
                    'assigned_technician_id' => $selectedTechnicianId,
                ]);

                return $booking->id;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existingBooking = $this->customerBookingsQuery()
                ->where('idempotency_key', $bookingIdempotencyKey)
                ->first();

            if ($existingBooking !== null) {
                $bookingId = $existingBooking->id;
            } else {
                report($exception);
                abort(409, 'This booking request key has already been used. Please retry the booking.');
            }
        }

        $this->resetBookingForm($validated['customer_phone']);
        if ($isMobileBookingFlow) {
            $this->moduleSlug = 'overview';
            $this->mobileHomeView = 'home';
        } else {
            $this->moduleSlug = 'my-bookings';
        }
        $this->successToast('Booking created successfully.');
    }

    public function openBooking(int $bookingId): void
    {
        $this->authorizeCustomer();
        abort_unless($this->customerBookingsQuery()->where('id', $bookingId)->exists(), 404);

        $this->selectedBookingId = $bookingId;
        $this->showBookingDetails = true;
    }

    public function openPayment(int $paymentId): void
    {
        $this->authorizeCustomer();
        abort_unless($this->customerPaymentsQuery()->whereKey($paymentId)->exists(), 404);

        $this->selectedPaymentId = $paymentId;
        $this->showPaymentDetails = true;
    }

    public function joinWalkInQueue(): void
    {
        $this->authorizeCustomer();
        if (! $this->walkInQueueAvailable()) {
            $this->errorToast('Walk-in queue is currently unavailable.');

            return;
        }

        if ($this->setting('maintenance_mode') === 'true') {
            $this->errorToast('Walk-in queue is temporarily unavailable during maintenance.');

            return;
        }

        $validated = Validator::make([
            'service_type' => $this->walkInServiceType,
            'phone' => $this->walkInPhone,
            'notes' => $this->walkInNotes,
        ], [
            'service_type' => ['required', Rule::in($this->serviceTypes())],
            'phone' => ['required', 'string', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $entry = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
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

                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    report($exception);
                    abort(409, 'The walk-in queue is busy. Please try again.');
                }
            }
        }

        abort_unless($entry instanceof WalkInEntry, 409, 'The walk-in queue is busy. Please try again.');

        $this->recordAudit('customer.walk_in_created', 'walk_in_entry', $entry->id, ['service_type' => $validated['service_type']]);
        $this->walkInServiceType = '';
        $this->walkInPhone = '';
        $this->walkInNotes = '';
        $this->successToast('You joined the walk-in queue.');
    }

    public function prepareCancellation(int $bookingId): void
    {
        $this->authorizeCustomer();
        $booking = $this->customerBookingsQuery()->whereKey($bookingId)->firstOrFail();

        if (! $this->canCancelBooking($booking)) {
            $this->errorToast('This booking can no longer be cancelled.');

            return;
        }

        $this->cancellationBookingId = $booking->id;
        $this->cancellationIdempotencyKey = Str::uuid()->toString();
        $this->cancellationReason = '';
        $this->showCancellation = true;
        $this->resetValidation();
    }

    public function cancelBooking(): void
    {
        $this->authorizeCustomer();
        $cancellationIdempotencyKey = $this->idempotencyKey($this->cancellationIdempotencyKey ?: Str::uuid()->toString());
        $this->cancellationIdempotencyKey = $cancellationIdempotencyKey;
        $validated = Validator::make([
            'reason' => $this->cancellationReason,
        ], [
            'reason' => ['required', 'string', 'max:500'],
        ])->validate();

        $booking = DB::transaction(function () use ($validated, $cancellationIdempotencyKey): Booking {
            $booking = $this->customerBookingsQuery()->lockForUpdate()->findOrFail($this->cancellationBookingId);

            if ($booking->statusHistory()->where('idempotency_key', $cancellationIdempotencyKey)->exists()) {
                return $booking;
            }

            abort_unless($this->canCancelBooking($booking), 409, 'This booking can no longer be cancelled.');

            $booking->update([
                'cancellation_reason' => trim($validated['reason']),
            ]);
            $booking->transitionTo('cancelled', auth()->user(), trim($validated['reason']), ['source' => 'customer'], $cancellationIdempotencyKey);
            $booking->update([
                'assigned_technician_id' => null,
            ]);

            return $booking;
        });

        $this->recordAudit('customer.booking_cancelled', 'booking', $booking->id, [
            'reason' => $booking->cancellation_reason,
        ]);
        $this->closeCancellation();
        $this->successToast('Booking cancelled successfully.');
    }

    public function closeCancellation(): void
    {
        $this->showCancellation = false;
        $this->cancellationBookingId = null;
        $this->cancellationIdempotencyKey = '';
        $this->cancellationReason = '';
        $this->resetValidation();
    }

    public function respondToQuotation(int $quotationId, string $status): void
    {
        $this->authorizeCustomer();
        $validatedStatus = Validator::make(['status' => $status], [
            'status' => ['required', Rule::in(['approved', 'rejected'])],
        ])->validate()['status'];
        abort_unless($this->tableExists('quotations'), 422, 'Quotations are not available yet.');

        $quotation = Quotation::query()
            ->whereKey($quotationId)
            ->whereHas('booking', fn (Builder $query): Builder => $query->whereBelongsTo(auth()->user(), 'customer'))
            ->firstOrFail();
        abort_unless($quotation->status === 'awaiting_approval', 409, 'This quotation has already been answered.');
        $quotation->update([
            'status' => $validatedStatus,
            'responded_at' => now(),
        ]);

        $this->recordAudit('customer.quotation_responded', 'quotation', $quotation->id, ['status' => $validatedStatus]);
        $this->successToast($validatedStatus === 'approved' ? 'Quotation approved.' : 'Quotation declined.');
    }

    public function closeBooking(): void
    {
        $this->selectedBookingId = null;
        $this->showBookingDetails = false;
    }

    public function closePaymentDetails(): void
    {
        $this->selectedPaymentId = null;
        $this->showPaymentDetails = false;
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

        $review = Review::query()->createOrFirst(['booking_id' => $booking->id], [
            'booking_id' => $booking->id,
            'customer_id' => auth()->id(),
            'technician_id' => $booking->assigned_technician_id,
            'rating' => $validated['rating'],
            'comment' => $validated['comment'],
            'status' => $this->setting('review_moderation') === 'true' ? 'pending' : 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (! $review->wasRecentlyCreated) {
            $this->reviewBookingId = null;
            $this->reviewRating = 0;
            $this->reviewComment = '';
            $this->errorToast('This booking already has a review.');

            return;
        }

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

        $ticket = null;
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
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

                break;
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === 2) {
                    report($exception);
                    abort(409, 'Support is busy. Please try again.');
                }
            }
        }

        abort_unless($ticket instanceof SupportTicket, 409, 'Support is busy. Please try again.');

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
            'quotations' => ['label' => 'Assessment stage', 'color' => 'amber'],
            'payments' => ['label' => 'Payment history', 'color' => 'emerald'],
            'ratings-reviews' => ['label' => 'Your feedback', 'color' => 'violet'],
            'notifications' => ['label' => 'Latest updates', 'color' => 'sky'],
            'support' => ['label' => 'Help center', 'color' => 'amber'],
            'settings' => ['label' => 'Account preferences', 'color' => 'zinc'],
            default => abort(404, 'Module not found.'),
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
            'quotations' => $this->quotationsContent(),
            'payments' => $this->paymentsContent(),
            'ratings-reviews' => $this->reviewsContent(),
            'notifications' => $this->notificationsContent(),
            'support' => $this->supportContent(),
            'settings' => $this->settingsContent(),
            default => abort(404, 'Module not found.'),
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
            'upcomingBookings' => $this->customerBookingsWithTechnicianQuery()->whereNotNull('bookings.scheduled_at')->where('bookings.scheduled_at', '>=', now())->whereNotIn('bookings.status', ['completed', 'cancelled', 'no_show'])->orderBy('bookings.scheduled_at')->limit(3)->get(),
            'popularServices' => $this->serviceCatalog(),
            'openSupport' => $openSupport,
        ];
    }

    /** @return array<string, mixed> */
    private function bookingFormContent(): array
    {
        return [
            'services' => $this->serviceCatalog(),
            'countryCallingCodes' => $this->countryCallingCodes(),
            'addressMapUrl' => $this->addressMapUrl(),
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
            $this->validateValue($this->bookingStatus, ['all', ...Booking::STATUSES]);
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
    private function walkInContent(): array
    {
        if (! $this->walkInQueueAvailable()) {
            return [
                'stats' => [
                    ['label' => 'Waiting', 'value' => '0', 'icon' => 'clock'],
                    ['label' => 'Being served', 'value' => '0', 'icon' => 'arrow-path'],
                    ['label' => 'Completed', 'value' => '0', 'icon' => 'check-circle'],
                ],
                'entries' => $this->emptyPagination('walkInPage'),
                'services' => $this->serviceCatalog(),
                'available' => false,
                'message' => 'Walk-in queue is currently unavailable. Please try again later.',
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
            'available' => true,
            'message' => '',
        ];
    }

    /** @return array<string, mixed> */
    private function quotationsContent(): array
    {
        if (! $this->tableExists('quotations')) {
            return [
                'stats' => [
                    ['label' => 'Awaiting assessment', 'value' => '0', 'icon' => 'clipboard-document-list'],
                    ['label' => 'Pending approval', 'value' => '0', 'icon' => 'clock'],
                    ['label' => 'Approved', 'value' => '0', 'icon' => 'check-circle'],
                ],
                'quotations' => $this->emptyPagination('quotationsPage'),
                'available' => false,
                'message' => 'Quotations appear here after a technician assessment. The quotation workflow is not available yet.',
            ];
        }

        $query = Quotation::query()
            ->with(['booking.service', 'technician:id,name'])
            ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->whereBelongsTo(auth()->user(), 'customer'));
        $base = clone $query;
        $awaitingAssessment = $this->customerBookingsQuery()
            ->whereIn('status', ['assigned', 'en_route', 'in_progress'])
            ->whereDoesntHave('quotation')
            ->count();

        return [
            'stats' => [
                ['label' => 'Awaiting assessment', 'value' => Number::format($awaitingAssessment), 'icon' => 'clipboard-document-list'],
                ['label' => 'Pending approval', 'value' => Number::format((clone $base)->where('status', 'awaiting_approval')->count()), 'icon' => 'clock'],
                ['label' => 'Approved', 'value' => Number::format((clone $base)->where('status', 'approved')->count()), 'icon' => 'check-circle'],
            ],
            'quotations' => $query->latest('created_at')->paginate(10, ['*'], 'quotationsPage'),
            'available' => true,
            'message' => 'Quotations appear here after a technician assessment.',
        ];
    }

    /** @return array<string, mixed> */
    private function paymentsContent(): array
    {
        if (! $this->tableExists('payments')) {
            return ['stats' => $this->paymentStats(), 'payments' => $this->emptyPagination('paymentsPage')];
        }

        $query = $this->customerPaymentsQuery();

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

        if ($this->tableExists('notifications')) {
            foreach (auth()->user()->notifications()->latest()->limit(10)->get() as $notification) {
                $data = $notification->data;
                $items->push([
                    'title' => $data['title'] ?? 'Booking update',
                    'detail' => $data['detail'] ?? 'Your service booking was updated.',
                    'status' => $data['status'] ?? 'info',
                    'date' => $notification->created_at,
                    'unread' => $notification->read_at === null,
                ]);
            }
        }

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
        $user = auth()->user();
        abort_unless($user instanceof User && $user->isCustomer() && $user->hasActiveAccount(), 403);
    }

    /** @return Builder<Booking> */
    private function customerBookingsQuery(): Builder
    {
        return Booking::query()->whereBelongsTo(auth()->user(), 'customer');
    }

    /** @return Builder<Payment> */
    private function customerPaymentsQuery(): Builder
    {
        return Payment::query()
            ->with(['booking:id,reference,service_type,status,user_id'])
            ->whereHas('booking', fn (Builder $bookingQuery): Builder => $bookingQuery->whereBelongsTo(auth()->user(), 'customer'));
    }

    /** @return Builder<Booking> */
    private function customerBookingsWithTechnicianQuery(): Builder
    {
        return Booking::query()
            ->select(['id', 'user_id', 'assigned_technician_id', 'reference', 'customer_name', 'customer_phone', 'service_type', 'booking_type', 'status', 'address', 'description', 'scheduled_at', 'latitude', 'longitude', 'created_at', 'updated_at'])
            ->with('technician:id,name,avatar_path,latitude,longitude')
            ->whereBelongsTo(auth()->user(), 'customer');
    }

    private function selectedBooking(): ?object
    {
        if ($this->selectedBookingId === null) {
            return null;
        }

        $query = $this->customerBookingsQuery();

        if ($this->tableExists('quotations')) {
            $query->with('quotation');
        }

        return $query->whereKey($this->selectedBookingId)->first();
    }

    private function selectedPayment(): ?Payment
    {
        if ($this->selectedPaymentId === null) {
            return null;
        }

        return $this->customerPaymentsQuery()->whereKey($this->selectedPaymentId)->first();
    }

    /** @return array<string, string> */
    private function countryCallingCodes(): array
    {
        return [
            '+63' => 'Philippines (+63)',
            '+1' => 'United States / Canada (+1)',
            '+44' => 'United Kingdom (+44)',
            '+61' => 'Australia (+61)',
            '+65' => 'Singapore (+65)',
            '+81' => 'Japan (+81)',
            '+82' => 'South Korea (+82)',
            '+86' => 'China (+86)',
            '+966' => 'Saudi Arabia (+966)',
            '+971' => 'United Arab Emirates (+971)',
        ];
    }

    private function normalizePhoneNumber(string $phone, string $countryCode): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $countryDigits = ltrim($countryCode, '+');

        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, $countryDigits)) {
            return '+'.$digits;
        }

        return '+'.$countryDigits.ltrim($digits, '0');
    }

    private function profilePhoneNumber(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '63') ? Str::after($digits, '63') : ltrim($digits, '0');
    }

    private function addressMapUrl(): ?string
    {
        if ($this->addressLatitude === null || $this->addressLongitude === null) {
            return null;
        }

        $latitudeDelta = 0.01;
        $longitudeDelta = 0.01;

        return 'https://www.openstreetmap.org/export/embed.html?'.http_build_query([
            'bbox' => implode(',', [
                $this->addressLongitude - $longitudeDelta,
                $this->addressLatitude - $latitudeDelta,
                $this->addressLongitude + $longitudeDelta,
                $this->addressLatitude + $latitudeDelta,
            ]),
            'layer' => 'mapnik',
            'marker' => $this->addressLatitude.','.$this->addressLongitude,
        ]);
    }

    private function walkInQueueAvailable(): bool
    {
        return $this->tableExists('walk_in_entries')
            && $this->columnExists('walk_in_entries', 'user_id')
            && $this->setting('walk_in_queue_enabled') !== 'false';
    }

    private function canCancelBooking(Booking $booking): bool
    {
        return in_array($booking->status, Booking::ACTIVE_STATUSES, true);
    }

    /** @return array<int, string> */
    private function serviceTypes(): array
    {
        return $this->serviceCatalog()->pluck('code')->map(static fn (mixed $code): string => (string) $code)->all();
    }

    /** @return array<int, string> */
    private function serviceCategories(): array
    {
        return $this->serviceCatalog()
            ->pluck('category')
            ->filter()
            ->unique()
            ->values()
            ->map(static fn (mixed $category): string => (string) $category)
            ->all();
    }

    /** @return Collection<int, User> */
    private function technicianStores(): Collection
    {
        if (! $this->tableExists('technician_verifications')) {
            return collect();
        }

        $technicianStores = once(fn (): Collection => User::query()
            ->select(['id', 'name'])
            ->where('role', 'technician')
            ->when($this->columnExists('users', 'account_status'), fn (Builder $query): Builder => $query->where('account_status', 'active'))
            ->whereHas('technicianVerification', function (Builder $query): void {
                $query->where('status', 'approved')
                    ->whereNotNull('address')
                    ->where('address', '<>', '');
            })
            ->with('technicianVerification:id,user_id,address,service_categories,service_area,status')
            ->orderBy('name')
            ->get());

        $search = Str::lower(trim($this->mobileTechnicianStoreSearch));

        if ($search === '') {
            return $technicianStores;
        }

        return $technicianStores->filter(function (User $technicianStore) use ($search): bool {
            $address = (string) $technicianStore->technicianVerification?->address;
            $searchable = Str::lower($technicianStore->name.' '.$address);

            return Str::contains($searchable, $search);
        })->values();
    }

    /** @return Collection<int, ServiceCatalog> */
    private function serviceCatalog(): Collection
    {
        return once(fn (): Collection => ServiceCatalog::activeCatalog());
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
        $catalogEntry = collect($this->serviceCatalog())->firstWhere('code', $service);

        return $catalogEntry instanceof ServiceCatalog
            ? (string) $catalogEntry->name
            : Str::headline((string) $service);
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

    /** @return LengthAwarePaginator<int, mixed> */
    private function emptyPagination(string $pageName): LengthAwarePaginator
    {
        return new LengthAwarePaginator([], 0, 10, LengthAwarePaginator::resolveCurrentPage($pageName), [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    private function resetBookingForm(?string $preservedPhone = null): void
    {
        $this->bookingType = 'quick';
        $this->selectedTechnicianId = null;
        $this->mobileTechnicianStoreSearch = '';
        $this->serviceCategory = '';
        $this->serviceType = '';
        $this->customerName = (string) auth()->user()->name;
        $this->customerPhone = $preservedPhone ?? $this->profilePhoneNumber((string) (auth()->user()->phone ?? ''));
        $this->mobileCountryCode = '+63';
        $this->address = '';
        $this->addressLatitude = null;
        $this->addressLongitude = null;
        $this->addressSuggestions = [];
        $this->scheduledAt = '';
        $this->description = '';
        $this->bookingIdempotencyKey = Str::uuid()->toString();
        $this->resetValidation();
    }

    private function idempotencyKey(string $key): string
    {
        $key = trim($key);
        abort_unless(Str::isUuid($key), 422, 'The request could not be verified. Please try again.');

        return $key;
    }

    /** @param array<int, mixed> $allowed */
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

    private function errorToast(string $message): void
    {
        Flux::toast(variant: 'danger', text: $message);
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
