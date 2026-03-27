<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enhance so_location_monthly_status table with workflow states:
     * - pending (default)
     * - submitted (after initial upload)
     * - approved (reviewer approved)
     * - rejected (hard reject, needs fresh upload)
     * - returned (soft reject, needs correction)
     */
    public function up(): void
    {
        Schema::table('so_location_monthly_status', function (Blueprint $table) {
            // Change status to ENUM with all 5 states
            $table->dropIndex('idx_so_location_status');
            
            // Drop the old status column and recreate as ENUM
            $table->dropColumn('status');
            
            // Add new status column as ENUM
            $table->enum('status', ['pending', 'submitted', 'approved', 'rejected', 'returned'])
                ->default('pending')
                ->after('wage_month')
                ->comment('Workflow state: pending|submitted|approved|rejected|returned');
            
            // Add submission_type to track how it was submitted
            $table->string('submission_type')->nullable()->after('status')
                ->comment('hard_copy|email|courier|soft_copy_upload');
            
            // Add reviewer remarks for rejections/returns
            $table->text('reviewer_remarks')->nullable()->after('remarks')
                ->comment('Remarks from reviewer when rejecting or returning');
            
            // Recreate status index
            $table->index('status', 'idx_so_location_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('so_location_monthly_status', function (Blueprint $table) {
            $table->dropIndex('idx_so_location_status');
            $table->dropColumn(['status', 'submission_type', 'reviewer_remarks']);
            
            // Restore original status field
            $table->string('status')
                ->default('pending')
                ->comment('pending, submitted, approved, rejected');
            
            $table->index('status', 'idx_so_location_status');
        });
    }
};
