<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexes('bookings', function (Blueprint $table): void {
            $table->index('updated_at', 'bookings_updated_at_index');
            $table->index(['status', 'updated_at'], 'bookings_status_updated_index');
            $table->index(['assigned_technician_id', 'status', 'updated_at'], 'bookings_technician_status_updated_index');
            $table->index(['user_id', 'updated_at'], 'bookings_user_updated_index');
        });

        $this->addIndexes('walk_in_entries', function (Blueprint $table): void {
            $table->index('updated_at', 'walk_in_entries_updated_at_index');
            $table->index(['status', 'updated_at'], 'walk_in_entries_status_updated_index');

            if (Schema::hasColumn('walk_in_entries', 'user_id')) {
                $table->index(['user_id', 'updated_at'], 'walk_in_entries_user_updated_index');
            }
        });

        $this->addIndexes('payments', function (Blueprint $table): void {
            $table->index('updated_at', 'payments_updated_at_index');
            $table->index(['status', 'updated_at'], 'payments_status_updated_index');
            $table->index(['booking_id', 'updated_at'], 'payments_booking_updated_index');
        });

        $this->addIndexes('technician_verifications', function (Blueprint $table): void {
            $table->index('updated_at', 'technician_verifications_updated_at_index');
            $table->index(['status', 'updated_at'], 'technician_verifications_status_updated_index');
        });

        $this->addIndexes('reviews', function (Blueprint $table): void {
            $table->index(['technician_id', 'status', 'updated_at'], 'reviews_technician_status_updated_index');
        });

        $this->addIndexes('support_tickets', function (Blueprint $table): void {
            $table->index(['status', 'updated_at'], 'support_tickets_status_updated_index');
        });

        if (Schema::hasTable('audit_logs') && Schema::hasColumn('audit_logs', 'created_at')) {
            Schema::table('audit_logs', function (Blueprint $table): void {
                $table->index('created_at', 'audit_logs_created_at_index');
            });
        }
    }

    public function down(): void
    {
        $this->dropIndexes('bookings', [
            'bookings_updated_at_index',
            'bookings_status_updated_index',
            'bookings_technician_status_updated_index',
            'bookings_user_updated_index',
        ]);
        $this->dropIndexes('walk_in_entries', [
            'walk_in_entries_updated_at_index',
            'walk_in_entries_status_updated_index',
            'walk_in_entries_user_updated_index',
        ]);
        $this->dropIndexes('payments', [
            'payments_updated_at_index',
            'payments_status_updated_index',
            'payments_booking_updated_index',
        ]);
        $this->dropIndexes('technician_verifications', [
            'technician_verifications_updated_at_index',
            'technician_verifications_status_updated_index',
        ]);
        $this->dropIndexes('reviews', ['reviews_technician_status_updated_index']);
        $this->dropIndexes('support_tickets', ['support_tickets_status_updated_index']);
        $this->dropIndexes('audit_logs', ['audit_logs_created_at_index']);
    }

    private function addIndexes(string $tableName, Closure $indexes): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        Schema::table($tableName, $indexes);
    }

    /** @param array<int, string> $indexes */
    private function dropIndexes(string $tableName, array $indexes): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        foreach ($indexes as $index) {
            try {
                Schema::table($tableName, function (Blueprint $table) use ($index): void {
                    $table->dropIndex($index);
                });
            } catch (Throwable) {
                // Legacy schemas may not have every optional index.
            }
        }
    }
};
