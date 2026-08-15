<?php

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('each workspace renders only its role-owned relational records', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer', 'name' => 'Live Customer']);
    $otherCustomer = User::factory()->create(['role' => 'customer', 'name' => 'Other Customer']);
    $technician = User::factory()->create(['role' => 'technician']);
    $otherTechnician = User::factory()->create(['role' => 'technician']);

    $customerBooking = Booking::create([
        'user_id' => $customer->id,
        'assigned_technician_id' => $technician->id,
        'reference' => 'LIVE-CUSTOMER-001',
        'customer_name' => $customer->name,
        'customer_phone' => '09170000001',
        'service_type' => 'plumbing',
        'booking_type' => 'scheduled',
        'status' => 'completed',
        'address' => 'Customer address',
        'scheduled_at' => now()->subDay(),
    ]);
    $otherBooking = Booking::create([
        'user_id' => $otherCustomer->id,
        'assigned_technician_id' => $otherTechnician->id,
        'reference' => 'LIVE-OTHER-001',
        'customer_name' => $otherCustomer->name,
        'customer_phone' => '09170000002',
        'service_type' => 'electrical',
        'booking_type' => 'scheduled',
        'status' => 'completed',
        'address' => 'Other address',
        'scheduled_at' => now()->subDay(),
    ]);

    Payment::create(['booking_id' => $customerBooking->id, 'amount' => 1500, 'status' => 'paid', 'method' => 'cash', 'paid_at' => now()]);
    Payment::create(['booking_id' => $otherBooking->id, 'amount' => 2200, 'status' => 'paid', 'method' => 'gcash', 'paid_at' => now()]);
    Review::create(['booking_id' => $customerBooking->id, 'customer_id' => $customer->id, 'technician_id' => $technician->id, 'rating' => 5, 'comment' => 'Customer review only', 'status' => 'published']);
    Review::create(['booking_id' => $otherBooking->id, 'customer_id' => $otherCustomer->id, 'technician_id' => $otherTechnician->id, 'rating' => 3, 'comment' => 'Other review only', 'status' => 'published']);

    $customerBookings = $this->actingAs($customer)->get(route('customer.module', ['module' => 'my-bookings']))->getContent();
    $customerPayments = $this->actingAs($customer)->get(route('customer.module', ['module' => 'payments']))->getContent();
    $customerReviews = $this->actingAs($customer)->get(route('customer.module', ['module' => 'ratings-reviews']))->getContent();
    $technicianJobs = $this->actingAs($technician)->get(route('technician.module', ['module' => 'my-jobs']))->getContent();
    $technicianEarnings = $this->actingAs($technician)->get(route('technician.module', ['module' => 'earnings']))->getContent();
    $technicianReviews = $this->actingAs($technician)->get(route('technician.module', ['module' => 'ratings-reviews']))->getContent();
    $adminBookings = $this->actingAs($admin)->get(route('admin.module', ['module' => 'service-bookings']))->getContent();

    expect($customerBookings)->toContain('LIVE-CUSTOMER-001')->not->toContain('LIVE-OTHER-001')
        ->and($customerPayments)->toContain('1,500.00')->not->toContain('2,200.00')
        ->and($customerReviews)->toContain('Customer review only')->not->toContain('Other review only')
        ->and($technicianJobs)->toContain('LIVE-CUSTOMER-001')->not->toContain('LIVE-OTHER-001')
        ->and($technicianEarnings)->toContain('1,500.00')->not->toContain('2,200.00')
        ->and($technicianReviews)->toContain('Customer review only')->not->toContain('Other review only')
        ->and($adminBookings)->toContain('LIVE-CUSTOMER-001')->toContain('LIVE-OTHER-001');
});

test('empty relations do not render generic people or unsupported placeholder metrics', function () {
    $admin = User::factory()->create(['role' => 'admin']);
    $customer = User::factory()->create(['role' => 'customer']);
    $booking = Booking::create([
        'user_id' => $customer->id,
        'reference' => 'LIVE-UNASSIGNED-001',
        'customer_name' => $customer->name,
        'customer_phone' => '09170000003',
        'service_type' => 'plumbing',
        'booking_type' => 'quick',
        'status' => 'completed',
        'address' => 'Unassigned address',
    ]);
    Review::create([
        'booking_id' => $booking->id,
        'customer_id' => $customer->id,
        'technician_id' => null,
        'rating' => 4,
        'comment' => null,
        'status' => 'published',
    ]);
    DB::table('support_tickets')->insert([
        'reference' => 'LIVE-SUPPORT-001',
        'user_id' => null,
        'subject' => 'Unassociated support case',
        'category' => 'technical',
        'priority' => 'normal',
        'status' => 'open',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $customerHtml = $this->actingAs($customer)->get(route('customer.module', ['module' => 'ratings-reviews']))->getContent();
    $adminHtml = $this->actingAs($admin)->get(route('admin.module', ['module' => 'support-disputes']))->getContent();
    $technicianHtml = $this->actingAs(User::factory()->create(['role' => 'technician']))->get(route('technician.module'))->getContent();

    expect($customerHtml)->not->toContain('>Technician</td>')
        ->and($adminHtml)->not->toContain('>Guest<')
        ->and($technicianHtml)->not->toContain('On-time arrival')
        ->and($technicianHtml)->not->toContain('Average response');
});
