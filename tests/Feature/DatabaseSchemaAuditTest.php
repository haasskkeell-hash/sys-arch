<?php

test('runtime schema contains the tables and columns used by core workflows', function () {
    $tables = [
        'users' => [
            'id', 'name', 'email', 'password', 'role', 'account_status', 'availability_status',
            'last_login_at', 'last_seen_at', 'latitude', 'longitude', 'google_id', 'phone',
            'avatar_path', 'created_at', 'updated_at',
        ],
        'bookings' => [
            'id', 'user_id', 'assigned_technician_id', 'reference', 'idempotency_key', 'customer_name',
            'customer_phone', 'service_type', 'booking_type', 'status', 'address', 'description',
            'scheduled_at', 'is_priority', 'internal_notes', 'cancellation_reason', 'latitude',
            'longitude', 'created_at', 'updated_at',
        ],
        'booking_status_histories' => [
            'id', 'booking_id', 'idempotency_key', 'from_status', 'to_status', 'actor_id', 'reason',
            'metadata', 'created_at',
        ],
        'technician_request_declines' => ['id', 'technician_id', 'booking_id', 'created_at', 'updated_at'],
        'payments' => ['id', 'booking_id', 'amount', 'status', 'method', 'transaction_ref', 'paid_at', 'created_at', 'updated_at'],
        'reviews' => ['id', 'booking_id', 'customer_id', 'technician_id', 'rating', 'comment', 'status', 'created_at', 'updated_at'],
        'notifications' => ['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'],
        'quotations' => ['id', 'booking_id', 'technician_id', 'assessment_notes', 'labor_amount', 'materials_amount', 'total_amount', 'status', 'sent_at', 'responded_at', 'created_at', 'updated_at'],
        'walk_in_entries' => ['id', 'user_id', 'queue_number', 'customer_name', 'customer_phone', 'service_type', 'priority', 'status', 'counter_id', 'notes', 'checked_in_at', 'called_at', 'service_started_at', 'completed_at', 'created_at', 'updated_at'],
        'support_tickets' => ['id', 'reference', 'user_id', 'subject', 'category', 'priority', 'status', 'assigned_to', 'latest_message', 'last_response_at', 'created_at', 'updated_at'],
        'technician_verifications' => ['id', 'user_id', 'reviewer_id', 'status', 'risk_level', 'service_categories', 'years_experience', 'service_area', 'phone', 'address', 'risk_flags', 'reviewer_notes', 'decision_reason', 'submitted_at', 'reviewed_at', 'created_at', 'updated_at'],
        'technician_documents' => ['id', 'verification_id', 'type', 'label', 'status', 'masked_number', 'expires_at', 'created_at', 'updated_at'],
        'service_counters' => ['id', 'name', 'staff_id', 'status', 'created_at', 'updated_at'],
        'service_catalog' => ['id', 'code', 'name', 'category', 'base_price', 'is_active', 'description', 'created_at', 'updated_at'],
        'platform_settings' => ['id', 'key', 'group', 'label', 'value', 'type', 'created_at', 'updated_at'],
        'audit_logs' => ['id', 'user_id', 'action', 'target_type', 'target_id', 'details', 'ip_address', 'created_at'],
    ];

    foreach ($tables as $table => $columns) {
        expect(Schema::hasTable($table))->toBeTrue("Missing runtime table: {$table}");

        foreach ($columns as $column) {
            expect(Schema::hasColumn($table, $column))->toBeTrue("Missing {$table}.{$column}");
        }
    }
});

test('runtime schema keeps core uniqueness and foreign-key constraints', function () {
    $indexes = static fn (string $table): array => collect(Schema::getIndexes($table))->pluck('name')->all();
    $foreignKeys = static function (string $table): array {
        return collect(Schema::getForeignKeys($table))
            ->map(static fn (array $foreignKey): string => implode(',', $foreignKey['columns']).'->'.$foreignKey['foreign_table'].'.'.implode(',', $foreignKey['foreign_columns']))
            ->all();
    };

    expect($indexes('bookings'))
        ->toContain('bookings_reference_unique', 'bookings_idempotency_key_unique');
    expect($indexes('booking_status_histories'))->toContain('booking_status_histories_idempotency_key_unique');
    expect($indexes('payments'))->toContain('payments_transaction_ref_unique', 'payments_booking_id_unique');
    expect($indexes('reviews'))->toContain('reviews_booking_id_unique');
    expect($indexes('technician_request_declines'))->toContain('technician_request_declines_technician_id_booking_id_unique');

    expect($foreignKeys('bookings'))
        ->toContain('user_id->users.id', 'assigned_technician_id->users.id');
    expect($foreignKeys('booking_status_histories'))
        ->toContain('booking_id->bookings.id', 'actor_id->users.id');
    expect($foreignKeys('technician_request_declines'))
        ->toContain('technician_id->users.id', 'booking_id->bookings.id');
});
