<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_monthly_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')
                ->constrained('locations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->date('wage_month')->comment('First day of the month (YYYY-MM-01)');
            $table->string('status')->default('pending')->comment('pending, submitted, approved, rejected');
            $table->string('submission_type')->nullable()->comment('hard_copy, email, courier, soft_copy_upload');
            $table->string('file_path')->nullable()->comment('Private storage path for uploaded files');
            $table->text('remarks')->nullable();
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->unique(['location_id', 'wage_month'], 'unique_location_month');
            $table->index('wage_month', 'idx_wage_month');
            $table->index('status', 'idx_status');
            $table->index('submitted_by', 'idx_submitted_by');
            $table->index('reviewed_by', 'idx_reviewed_by');
            $table->index(['location_id', 'status'], 'idx_location_status');
        });

        // Add foreign key to service_order_location to reference location_monthly_status if needed
        if (Schema::hasTable('service_order_location')) {
            Schema::table('service_order_location', function (Blueprint $table) {
                // Add reference to location_monthly_status for month-based tracking
                if (!Schema::hasColumn('service_order_location', 'location_monthly_status_id')) {
                    $table->foreignId('location_monthly_status_id')
                        ->nullable()
                        ->after('location_id')
                        ->constrained('location_monthly_status')
                        ->nullOnDelete()
                        ->cascadeOnUpdate();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('service_order_location')) {
            Schema::table('service_order_location', function (Blueprint $table) {
                if (Schema::hasColumn('service_order_location', 'location_monthly_status_id')) {
                    $table->dropConstrainedForeignId('location_monthly_status_id');
                }
            });
        }

        Schema::dropIfExists('location_monthly_status');
    }
};
