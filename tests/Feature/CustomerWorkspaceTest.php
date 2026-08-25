<?php

use App\Livewire\Customer\ModulePage;
use App\Models\PlatformSetting;
use App\Models\Review;
use App\Models\ServiceCatalog;
use App\Models\User;
use App\Notifications\BookingStatusChanged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

function customerBooking(int $customerId, string $status = 'pending', ?int $technicianId = null): int
{
    return DB::table('bookings')->insertGetId([
        'user_id' => $customerId,
        'assigned_technician_id' => $technicianId,
        'reference' => 'CUST-'.fake()->unique()->numerify('#####'),
        'customer_name' => 'FixTrack Customer',
        'customer_phone' => '09170000000',
        'service_type' => 'plumbing',
        'booking_type' => 'quick',
        'status' => $status,
        'address' => 'Quezon City',
        'description' => 'Repair kitchen plumbing.',
        'scheduled_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('customers are redirected to their workspace and see customer modules', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('dashboard'))
        ->assertRedirect(route('customer.module'));

    $this->actingAs($customer)
        ->get(route('customer.module'))
        ->assertOk()
        ->assertSee('Book a Service')
        ->assertSee('My Bookings')
        ->assertSee('Quotations')
        ->assertSee('Ratings &amp; Reviews', false)
        ->assertDontSee('System Health');
});

test('customer module routes expose the migrated customer content', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    foreach ([
        'book-service' => ['Book a Service', 'Request a technician'],
        'my-bookings' => ['My Bookings', 'Booking history'],
        'quotations' => ['Quotations', 'No quotations to review'],
        'payments' => ['Payments', 'Payment history'],
        'ratings-reviews' => ['Ratings & Reviews', 'Your reviews'],
        'notifications' => ['Notifications', 'Latest updates'],
        'support' => ['Support & Help', 'Contact support'],
        'settings' => ['Settings', 'Account preferences'],
    ] as $slug => [$label, $content]) {
        $this->actingAs($customer)
            ->get(route('customer.module', ['module' => $slug]))
            ->assertOk()
            ->assertSee($label)
            ->assertSee($content);
    }
});

test('customers can review their payment summary and open only their payment details', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $bookingId = customerBooking($customer->id, 'completed');
    $otherBookingId = customerBooking($otherCustomer->id, 'completed');

    $paymentId = DB::table('payments')->insertGetId([
        'booking_id' => $bookingId,
        'amount' => 1500,
        'status' => 'paid',
        'method' => 'GCash',
        'transaction_ref' => 'FT-PAY-1001',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $otherPaymentId = DB::table('payments')->insertGetId([
        'booking_id' => $otherBookingId,
        'amount' => 900,
        'status' => 'paid',
        'method' => 'Cash',
        'transaction_ref' => 'FT-PAY-1002',
        'paid_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $component = Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'payments'])
        ->assertSee('Total paid')
        ->assertSee('₱1,500.00')
        ->assertSee('GCash')
        ->assertSee('View details')
        ->call('openPayment', $paymentId)
        ->assertSet('showPaymentDetails', true)
        ->assertSee('FT-PAY-1001');

    expect(fn () => (new ModulePage)->openPayment($otherPaymentId))
        ->toThrow(HttpException::class);
});

test('customers see an unavailable state instead of a broken walk-in queue form', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    PlatformSetting::create([
        'key' => 'walk_in_queue_enabled',
        'group' => 'booking',
        'label' => 'Walk-in queue',
        'value' => 'false',
        'type' => 'boolean',
    ]);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'walk-in-queue'])
        ->assertSee('Walk-in queue is currently unavailable')
        ->assertDontSee('Check in for an in-person service visit.');
});

test('customers can create a quick booking with the source validation rules', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    ServiceCatalog::create([
        'code' => 'aircon',
        'name' => 'Aircon cleaning',
        'category' => 'Cooling',
        'base_price' => 1800,
        'is_active' => true,
    ]);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'book-service'])
        ->set('customerName', 'Maria Santos')
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'aircon')
        ->set('bookingType', 'quick')
        ->set('address', 'Makati City')
        ->set('description', 'The unit needs cleaning.')
        ->call('createBooking')
        ->assertSet('moduleSlug', 'my-bookings')
        ->assertDispatched('toast-show', function (string $event, array $params): bool {
            return data_get($params, 'dataset.variant') === 'success';
        });

    expect(DB::table('bookings')->where('user_id', $customer->id)->first())
        ->service_type->toBe('aircon')
        ->status->toBe('matching')
        ->booking_type->toBe('quick');
});

test('customers see the restored default service categories in the booking form', function () {
    $this->seed();
    $customer = User::factory()->create(['role' => 'customer']);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'book-service'])
        ->assertSee('Aircon cleaning')
        ->assertSee('Appliance repair')
        ->assertSee('Electrical repair')
        ->assertSee('Plumbing repair');
});

test('customers keep default service categories when the catalog table is empty', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->call('openMobileQuickBook')
        ->assertSee('Aircon cleaning')
        ->assertSee('Appliance repair')
        ->assertSee('Electrical repair')
        ->assertSee('Plumbing repair');
});

test('customers choose a service category before choosing the exact service', function () {
    $this->seed();
    $customer = User::factory()->create(['role' => 'customer']);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'book-service'])
        ->assertSee('Choose a service category')
        ->call('chooseServiceCategory', 'Electronics')
        ->assertSet('serviceCategory', 'Electronics')
        ->assertSee('LCD / screen replacement')
        ->assertSee('Battery replacement')
        ->assertSee('Not sure — diagnose my device')
        ->call('chooseServiceType', 'electronics-lcd')
        ->assertSet('serviceType', 'electronics-lcd')
        ->assertSet('serviceCategory', 'Electronics');
});

test('mobile customers use the same category then exact service flow', function () {
    $this->seed();
    $customer = User::factory()->create(['role' => 'customer']);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->call('openMobileQuickBook')
        ->assertSee('Choose a service category')
        ->call('chooseServiceCategory', 'Electronics')
        ->assertSee('LCD / screen replacement')
        ->call('chooseServiceType', 'electronics-battery')
        ->assertSet('serviceType', 'electronics-battery');
});

test('booking uses the profile name, normalizes the phone, and stores the selected map location', function () {
    $customer = User::factory()->create(['role' => 'customer', 'name' => 'Maria Santos']);
    ServiceCatalog::create([
        'code' => 'aircon',
        'name' => 'Aircon cleaning',
        'category' => 'Cooling',
        'base_price' => 1800,
        'is_active' => true,
    ]);
    Http::fake([
        'https://nominatim.openstreetmap.org/*' => Http::response([
            ['display_name' => 'Makati City, Metro Manila, Philippines', 'lat' => '14.5547', 'lon' => '121.0244'],
        ]),
    ]);

    $component = Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'book-service'])
        ->set('customerName', 'A tampered name')
        ->set('mobileCountryCode', '+63')
        ->set('customerPhone', '9171234567')
        ->set('serviceType', 'aircon')
        ->set('address', 'Makati')
        ->call('searchAddress')
        ->call('selectAddress', 0)
        ->call('createBooking');

    $component
        ->assertSet('customerPhone', '+639171234567')
        ->call('openBooking', DB::table('bookings')->where('user_id', $customer->id)->value('id'))
        ->assertSee('Mobile number')
        ->assertSee('+639171234567');

    $booking = DB::table('bookings')->where('user_id', $customer->id)->first();

    expect($booking)
        ->customer_name->toBe('Maria Santos')
        ->customer_phone->toBe('+639171234567')
        ->address->toBe('Makati City, Metro Manila, Philippines');
    expect((float) $booking->latitude)->toBe(14.5547)
        ->and((float) $booking->longitude)->toBe(121.0244);
});

test('address search retries a provider failure before showing no results', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    Http::fake([
        'https://nominatim.openstreetmap.org/*' => Http::response([], 503),
    ]);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'book-service'])
        ->set('address', 'Makati')
        ->call('searchAddress')
        ->assertSet('addressSuggestions', []);

    Http::assertSentCount(2);
});

test('desktop booking exposes the draggable auto-location map', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('customer.module', ['module' => 'book-service']))
        ->assertOk()
        ->assertSee('data-app-customer-booking-map', false)
        ->assertSee('data-map-auto-locate="true"', false)
        ->assertSee('data-map-draggable="true"', false)
        ->assertSee('data-app-map-latitude', false)
        ->assertSee('Drag the pin to fine-tune');
});

test('customer coordinates automatically update the service address through reverse geocoding', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    Http::fake([
        'https://nominatim.openstreetmap.org/reverse*' => Http::response([
            'display_name' => 'Makati City, Metro Manila, Philippines',
            'lat' => '14.5547',
            'lon' => '121.0244',
        ]),
    ]);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->call('openMobileQuickBook')
        ->call('updateLocationFromCoordinates', 14.5547, 121.0244)
        ->assertSet('address', 'Makati City, Metro Manila, Philippines')
        ->assertSet('addressLatitude', 14.5547)
        ->assertSet('addressLongitude', 121.0244);

    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://nominatim.openstreetmap.org/reverse')
        && (float) $request['lat'] === 14.5547
        && (float) $request['lon'] === 121.0244);
});

test('customer can keep the pin coordinates when reverse geocoding is unavailable', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    Http::fake([
        'https://nominatim.openstreetmap.org/reverse*' => Http::response([], 503),
    ]);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->call('openMobileQuickBook')
        ->call('updateLocationFromCoordinates', 14.5547, 121.0244)
        ->assertSet('address', '')
        ->assertSet('addressLatitude', 14.5547)
        ->assertSet('addressLongitude', 121.0244)
        ->assertHasErrors('address')
        ->set('address', 'Manual address')
        ->assertHasNoErrors('address');
});

test('replaying a customer booking request reuses the existing booking', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    ServiceCatalog::create([
        'code' => 'plumbing',
        'name' => 'Plumbing',
        'category' => 'Home repair',
        'base_price' => 500,
        'is_active' => true,
    ]);
    $requestKey = '22222222-2222-4222-8222-222222222222';

    $component = Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'book-service'])
        ->set('bookingIdempotencyKey', $requestKey)
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'plumbing')
        ->set('address', 'Makati City')
        ->call('createBooking');

    $component
        ->set('bookingIdempotencyKey', $requestKey)
        ->set('customerPhone', '09171234567')
        ->set('serviceType', 'plumbing')
        ->set('address', 'Makati City')
        ->call('createBooking');

    expect(DB::table('bookings')->where('user_id', $customer->id)->count())->toBe(1);
});

test('customers can cancel an eligible booking with a reason', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = customerBooking($customer->id, 'assigned', $technician->id);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'my-bookings'])
        ->call('prepareCancellation', $bookingId)
        ->set('cancellationReason', 'My schedule changed.')
        ->call('cancelBooking');

    expect(DB::table('bookings')->where('id', $bookingId)->first())
        ->status->toBe('cancelled')
        ->cancellation_reason->toBe('My schedule changed.')
        ->assigned_technician_id->toBeNull();
});

test('customers can cancel a booking after service has started', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = customerBooking($customer->id, 'in_progress', $technician->id);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'overview'])
        ->assertSee('Cancel booking')
        ->call('prepareCancellation', $bookingId)
        ->set('cancellationReason', 'I need to stop the service.')
        ->call('cancelBooking');

    expect(DB::table('bookings')->where('id', $bookingId)->first())
        ->status->toBe('cancelled')
        ->assigned_technician_id->toBeNull()
        ->and($technician->notifications()->where('type', BookingStatusChanged::class)->count())->toBe(1);
});

test('customers can approve a quotation attached to their booking', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = customerBooking($customer->id, 'in_progress', $technician->id);
    $quotationId = DB::table('quotations')->insertGetId([
        'booking_id' => $bookingId,
        'technician_id' => $technician->id,
        'assessment_notes' => 'Replace the damaged valve.',
        'labor_amount' => 800,
        'materials_amount' => 450,
        'total_amount' => 1250,
        'status' => 'awaiting_approval',
        'sent_at' => now(),
        'responded_at' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'quotations'])
        ->assertSee('Approve')
        ->call('respondToQuotation', $quotationId, 'approved');

    expect(DB::table('quotations')->where('id', $quotationId)->value('status'))->toBe('approved');
});

test('the duplicate addresses module is no longer exposed', function () {
    $customer = User::factory()->create(['role' => 'customer']);

    $this->actingAs($customer)
        ->get(route('customer.module', ['module' => 'addresses']))
        ->assertNotFound();
});

test('customers can submit one review for a completed booking', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = customerBooking($customer->id, 'completed', $technician->id);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'ratings-reviews'])
        ->call('startReview', $bookingId)
        ->set('reviewRating', 5)
        ->set('reviewComment', 'Great service.')
        ->call('createReview')
        ->assertSet('reviewBookingId', null);

    expect(DB::table('reviews')->where('booking_id', $bookingId)->first())
        ->customer_id->toBe($customer->id)
        ->rating->toBe(5)
        ->comment->toBe('Great service.');
});

test('a duplicate review submission returns a stable response instead of a server error', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $bookingId = customerBooking($customer->id, 'completed', $technician->id);
    Review::create([
        'booking_id' => $bookingId,
        'customer_id' => $customer->id,
        'technician_id' => $technician->id,
        'rating' => 5,
        'comment' => 'Already submitted.',
        'status' => 'published',
    ]);

    $this->actingAs($customer);
    $component = new ModulePage;
    $component->reviewBookingId = $bookingId;
    $component->reviewRating = 4;
    $component->reviewComment = 'A concurrent duplicate.';

    expect(fn () => $component->createReview())->not->toThrow(HttpException::class);

    expect(DB::table('reviews')->where('booking_id', $bookingId)->count())->toBe(1);
});

test('customers can submit a support request and cannot open another customers booking', function () {
    $customer = User::factory()->create(['role' => 'customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer']);
    $bookingId = customerBooking($otherCustomer->id);

    $this->actingAs($customer);

    expect(fn () => (new ModulePage)->openBooking($bookingId))
        ->toThrow(HttpException::class);

    Livewire::actingAs($customer)
        ->test(ModulePage::class, ['module' => 'support'])
        ->set('supportSubject', 'Booking question')
        ->set('supportCategory', 'booking')
        ->set('supportPriority', 'normal')
        ->set('supportMessage', 'Can you help with my booking?')
        ->call('createSupportTicket');

    expect(DB::table('support_tickets')->where('user_id', $customer->id)->first())
        ->subject->toBe('Booking question')
        ->status->toBe('open');
});
