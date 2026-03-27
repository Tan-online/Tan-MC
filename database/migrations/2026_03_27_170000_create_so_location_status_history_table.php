<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create so_location_status_history table to track all workflow actions
     * 
     * Maintains audit trail for:
     * - Initial submission (pending -> submitted)
     * - Approval (submitted -> approved)
     * - Rejection (submitted -> rejected)
     * - Return for correction (submitted -> returned)
     * - Resubmission (rejected/returned -> submitted)
     */
    public function up(): void
    {
        Schema::create('so_location_status_history', function (Blueprint $table) {
            $table->id();
            
            // Reference to service order location
            $table->foreignId('service_order_id')
                ->constrained('service_orders')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            
            $table->foreignId('location_id')
                ->constrained('locations')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            
            // Month being tracked
            $table->string('wage_month')->comment('YYYY-MM format');
            
            // Status recorded at this action
            $table->enum('status', ['pending', 'submitted', 'approved', 'rejected', 'returned'])
                ->comment('The status after this action');
            
            // Remarks for this action
            $table->text('remarks')->nullable()
                ->comment('Details about this action (submission notes, rejection reason, etc.)');
            
            // Who performed the action
            $table->foreignId('action_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete()
                ->cascadeOnUpdate()
                ->comment('User who performed this action');
            
            // When the action occurred
            $table->timestamp('action_at')
                ->useCurrent()
                ->comment('When this action was recorded');
            
            // System timestamps
            $table->timestamps();
            
            // Indexes for optimized queries
            $table->index('service_order_id', 'idx_history_so_id');
            $table->index('location_id', 'idx_history_location_id');
            $table->index('wage_month', 'idx_history_wage_month');
            $table->index('status', 'idx_history_status');
            $table->index('action_by', 'idx_history_action_by');
            $table->index('action_at', 'idx_history_action_at');
            
            // Composite indexes
            $table->index(['service_order_id', 'location_id', 'wage_month'], 'idx_history_so_location_month');
            $table->index(['service_order_id', 'location_id'], 'idx_history_so_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('so_location_status_history');
    }
};
