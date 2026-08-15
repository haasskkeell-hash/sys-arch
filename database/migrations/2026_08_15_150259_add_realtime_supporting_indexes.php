<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('reviews', ['customer_id', 'status', 'updated_at'], 'reviews_customer_status_updated_index');
        $this->addIndex('technician_documents', ['status', 'updated_at'], 'technician_documents_status_updated_index');
        $this->addIndex('service_counters', ['status', 'updated_at'], 'service_counters_status_updated_index');
        $this->addIndex('support_tickets', ['user_id', 'status', 'updated_at'], 'support_tickets_user_status_updated_index');
        $this->addIndex('audit_logs', ['user_id', 'created_at'], 'audit_logs_user_created_index');
        $this->addIndex('payments', ['status', 'paid_at'], 'payments_status_paid_at_index');
        $this->addIndex('bookings', ['scheduled_at', 'status'], 'bookings_scheduled_status_index');
        $this->addIndex('walk_in_entries', ['status', 'checked_in_at'], 'walk_in_entries_status_checked_in_index');
        $this->addIndex('technician_verifications', ['risk_level', 'updated_at'], 'technician_verifications_risk_updated_index');
        $this->addIndex('users', ['role', 'availability_status', 'updated_at'], 'users_role_availability_updated_index');
    }

    public function down(): void
    {
        foreach ([
            'reviews' => ['reviews_customer_status_updated_index'],
            'technician_documents' => ['technician_documents_status_updated_index'],
            'service_counters' => ['service_counters_status_updated_index'],
            'support_tickets' => ['support_tickets_user_status_updated_index'],
            'audit_logs' => ['audit_logs_user_created_index'],
            'payments' => ['payments_status_paid_at_index'],
            'bookings' => ['bookings_scheduled_status_index'],
            'walk_in_entries' => ['walk_in_entries_status_checked_in_index'],
            'technician_verifications' => ['technician_verifications_risk_updated_index'],
            'users' => ['users_role_availability_updated_index'],
        ] as $table => $indexes) {
            foreach ($indexes as $index) {
                $this->dropIndex($table, $index);
            }
        }
    }

    /** @param array<int, string> $columns */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || collect(Schema::getIndexes($table))->pluck('name')->contains($name)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! collect(Schema::getIndexes($table))->pluck('name')->contains($name)) {
            return;
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($name): void {
                $blueprint->dropIndex($name);
            });
        } catch (Throwable) {
            // Legacy schemas may not support dropping optional indexes.
        }
    }
};
