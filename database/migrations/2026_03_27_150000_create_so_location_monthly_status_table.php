<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This creates the so_location_monthly_status table for tracking
     * service order + location + wage month combinations independently.
     * 
     * Key design:
     * - UNIQUE (service_order_id, location_id, wage_month)
     * - Allows same location in different SOs to have independent status
     * - Allows same SO in different months to have independent status
     */
    public function up(): void
    {
        Schema::create('so_location_monthly_status', function (Blueprint $table) {
            $table->id();
            
            // Foreign keys - composite unique key
            $table->foreignId('service_order_id')
                ->constrained('service_orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            
            $table->foreignId('location_id')
                ->constrained('locations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            
            // Date field for YYYY-MM format (stored as first day of month)
            $table->string('wage_month')->comment('YYYY-MM format (e.g., 2026-03)');
            
            // Status field
            $table->string('status')
                ->default('pending')
                ->comment('pending, submitted, approved, rejected');
            
            // File upload details
            $table->string('file_path')->nullable()->comment('Private storage path for uploaded files');
            $table->text('remarks')->nullable();
            
            // Submission tracking
            $table->foreignId('submitted_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            
            $table->timestamp('submitted_at')->nullable();
            
            // Approval/review tracking
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            
            $table->timestamp('reviewed_at')->nullable();
            
            // System timestamps
            $table->timestamps();

            // UNIQUE constraint: one record per (service_order_id, location_id, wage_month)
            $table->unique(
                ['service_order_id', 'location_id', 'wage_month'],
                'unique_so_location_wage_month'
            );

            // Performance indexes
            $table->index('service_order_id', 'idx_so_location_so_id');
            $table->index('location_id', 'idx_so_location_location_id');
            $table->index('wage_month', 'idx_so_location_wage_month');
            $table->index('status', 'idx_so_location_status');
            $table->index('submitted_by', 'idx_so_location_submitted_by');
            $table->index('reviewed_by', 'idx_so_location_reviewed_by');
            
            // Composite indexes for common queries
            $table->index(['service_order_id', 'location_id'], 'idx_so_location_composite');
            $table->index(['service_order_id', 'wage_month'], 'idx_so_wage_month');
            $table->index(['location_id', 'wage_month'], 'idx_location_wage_month');
            $table->index(['service_order_id', 'location_id', 'status'], 'idx_so_location_status_composite');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('so_location_monthly_status');
    }
};
