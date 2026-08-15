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
        if (! Schema::hasTable('bookings')) {
            Schema::create('bookings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('assigned_technician_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reference')->unique();
                $table->string('customer_name');
                $table->string('customer_phone', 30);
                $table->string('service_type', 30);
                $table->string('booking_type', 20);
                $table->string('status', 30)->index();
                $table->text('address');
                $table->text('description')->nullable();
                $table->timestamp('scheduled_at')->nullable();
                $table->boolean('is_priority')->default(false)->index();
                $table->text('internal_notes')->nullable();
                $table->text('cancellation_reason')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('action');
                $table->string('target_type');
                $table->unsignedBigInteger('target_id')->nullable();
                $table->json('details')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        if (! Schema::hasTable('technician_verifications')) {
            Schema::create('technician_verifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 30)->default('submitted')->index();
                $table->string('risk_level', 20)->default('low');
                $table->json('service_categories');
                $table->unsignedTinyInteger('years_experience')->default(0);
                $table->string('service_area')->nullable();
                $table->string('phone', 30)->nullable();
                $table->text('address')->nullable();
                $table->json('risk_flags')->nullable();
                $table->text('reviewer_notes')->nullable();
                $table->text('decision_reason')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('technician_documents')) {
            Schema::create('technician_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('verification_id')->constrained('technician_verifications')->cascadeOnDelete();
                $table->string('type', 40);
                $table->string('label');
                $table->string('status', 20)->default('pending');
                $table->string('masked_number')->nullable();
                $table->date('expires_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('service_counters')) {
            Schema::create('service_counters', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('available')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('walk_in_entries')) {
            Schema::create('walk_in_entries', function (Blueprint $table) {
                $table->id();
                $table->string('queue_number')->unique();
                $table->string('customer_name');
                $table->string('customer_phone', 30)->nullable();
                $table->string('service_type', 30);
                $table->string('priority', 20)->default('standard');
                $table->string('status', 20)->default('waiting')->index();
                $table->foreignId('counter_id')->nullable()->constrained('service_counters')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamp('checked_in_at');
                $table->timestamp('called_at')->nullable();
                $table->timestamp('service_started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->string('status', 20)->default('pending')->index();
                $table->string('method', 30)->nullable();
                $table->string('transaction_ref')->nullable()->unique();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('reviews')) {
            Schema::create('reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->unique()->constrained()->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('technician_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedTinyInteger('rating');
                $table->text('comment')->nullable();
                $table->string('status', 20)->default('published')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('support_tickets')) {
            Schema::create('support_tickets', function (Blueprint $table) {
                $table->id();
                $table->string('reference')->unique();
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $table->string('subject');
                $table->string('category', 30);
                $table->string('priority', 20)->default('normal');
                $table->string('status', 20)->default('open')->index();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->text('latest_message')->nullable();
                $table->timestamp('last_response_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('service_catalog')) {
            Schema::create('service_catalog', function (Blueprint $table) {
                $table->id();
                $table->string('code')->unique();
                $table->string('name');
                $table->string('category');
                $table->decimal('base_price', 10, 2)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('group');
                $table->string('label');
                $table->text('value')->nullable();
                $table->string('type', 20)->default('text');
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /**
         * Shared legacy tables are intentionally preserved on rollback.
         */
    }
};
