<?php

use App\Livewire\Customer\ModulePage;
use App\Models\ServiceCatalog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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
        'addresses' => ['Addresses', 'Service addresses'],
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
