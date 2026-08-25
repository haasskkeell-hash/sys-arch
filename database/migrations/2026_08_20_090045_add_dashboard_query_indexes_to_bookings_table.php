<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->addIndex('bookings', ['user_id', 'created_at'], 'bookings_user_created_index');
        $this->addIndex('bookings', ['assigned_technician_id', 'created_at'], 'bookings_technician_created_index');
        $this->addIndex('bookings', ['assigned_technician_id', 'status', 'created_at'], 'bookings_assignment_status_created_index');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach ([
            'bookings_user_created_index',
            'bookings_technician_created_index',
            'bookings_assignment_status_created_index',
        ] as $index) {
            if (! Schema::hasTable('bookings') || ! collect(Schema::getIndexes('bookings'))->pluck('name')->contains($index)) {
                continue;
            }

            Schema::table('bookings', function (Blueprint $table) use ($index): void {
                $table->dropIndex($index);
            });
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
};
