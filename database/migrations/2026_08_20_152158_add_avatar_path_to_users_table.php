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
        if (! Schema::hasTable('users') || Schema::hasColumn('users', 'avatar_path')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->string('avatar_path')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /**
         * Keep profile media when rolling back compatibility migrations.
         */
    }
};
