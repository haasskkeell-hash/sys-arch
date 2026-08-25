<?php

use App\Models\ServiceCatalog;
use Illuminate\Support\Facades\DB;

test('the application seeder restores service catalog defaults without demo operational records', function () {
    $this->seed();

    expect(DB::table('users')->count())->toBe(0)
        ->and(ServiceCatalog::query()->pluck('code')->sort()->values()->all())->toBe([
            'aircon',
            'aircon-diagnosis',
            'appliance',
            'appliance-diagnosis',
            'electrical',
            'electrical-diagnosis',
            'electronics-battery',
            'electronics-diagnosis',
            'electronics-lcd',
            'plumbing',
            'plumbing-diagnosis',
        ])
        ->and(DB::table('bookings')->count())->toBe(0)
        ->and(DB::table('technician_verifications')->count())->toBe(0)
        ->and(DB::table('payments')->count())->toBe(0)
        ->and(DB::table('reviews')->count())->toBe(0)
        ->and(DB::table('walk_in_entries')->count())->toBe(0)
        ->and(DB::table('support_tickets')->count())->toBe(0)
        ->and(DB::table('audit_logs')->count())->toBe(0);
});
