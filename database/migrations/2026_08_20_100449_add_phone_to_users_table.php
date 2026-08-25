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
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'phone')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /**
         * The phone field may predate this compatibility migration in an existing database.
         * Keep it on rollback so profile data cannot be removed accidentally.
         */
    }
};
