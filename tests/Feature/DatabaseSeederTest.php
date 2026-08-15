<?php

use Illuminate\Support\Facades\DB;

test('the application seeder does not create fixed operational demo records', function () {
    $this->seed();

    expect(DB::table('users')->count())->toBe(0)
        ->and(DB::table('service_catalog')->count())->toBe(0)
        ->and(DB::table('bookings')->count())->toBe(0)
        ->and(DB::table('technician_verifications')->count())->toBe(0)
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(DB::table('reviews')->count())->toBe(0)
        ->and(DB::table('walk_in_entries')->count())->toBe(0)
        ->and(DB::table('support_tickets')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});
