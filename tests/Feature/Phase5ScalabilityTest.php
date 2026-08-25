<?php

use Illuminate\Support\Facades\Schema;

test('customer and technician booking queries have ordering indexes', function () {
    $indexes = collect(Schema::getIndexes('bookings'))->pluck('name');

    expect($indexes)
        ->toContain('bookings_user_created_index')
        ->toContain('bookings_technician_created_index')
        ->toContain('bookings_assignment_status_created_index');
});

test('slow database request monitoring is registered', function () {
    $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

    expect($source)
        ->toContain('whenQueryingForLongerThan(500')
        ->toContain("Log::warning('Slow database request detected.'");
});

test('slow database telemetry names cumulative and individual timings separately', function () {
    $source = file_get_contents(app_path('Providers/AppServiceProvider.php'));

    expect($source)
        ->toContain("'cumulative_threshold_ms' => 500")
        ->toContain("'last_query_ms' => \$event->time");
});

test('database failures have structured alert context', function () {
    $source = file_get_contents(base_path('bootstrap/app.php'));

    expect($source)
        ->toContain("'event' => 'database_operation_failed'")
        ->toContain("Log::error('Database operation failed.'");
});
