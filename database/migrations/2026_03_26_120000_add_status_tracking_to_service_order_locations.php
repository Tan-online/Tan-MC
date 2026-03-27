<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add status tracking fields to service_order_location table
        Schema::table('service_order_location', function (Blueprint $table) {
            if (!Schema::hasColumn('service_order_location', 'status')) {
                $table->string('status')->default('pending')->after('muster_due_days');
            }
            if (!Schema::hasColumn('service_order_location', 'type')) {
                $table->string('type')->nullable()->after('status');
            }
            if (!Schema::hasColumn('service_order_location', 'remarks')) {
                $table->text('remarks')->nullable()->after('type');
            }
            if (!Schema::hasColumn('service_order_location', 'action_date')) {
                $table->timestamp('action_date')->nullable()->after('remarks');
            }
            if (!Schema::hasColumn('service_order_location', 'action_by_id')) {
                $table->foreignId('action_by_id')
                    ->nullable()
                    ->after('action_date')
                    ->constrained('users')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            }
        });

        // Create status history table if it doesn't exist
        if (!Schema::hasTable('service_order_location_status_history')) {
            Schema::create('service_order_location_status_history', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_order_location_id')
                    ->constrained('service_order_location')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
                $table->string('status');
                $table->text('remarks')->nullable();
                $table->foreignId('changed_by_id')
                    ->constrained('users')
                    ->restrictOnDelete()
                    ->cascadeOnUpdate();
                $table->timestamps();

                $table->index(['service_order_location_id', 'created_at'], 'sol_status_hist_idx');
                $table->index('changed_by_id', 'sol_status_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('service_order_location_status_history');

        Schema::table('service_order_location', function (Blueprint $table) {
            $table->dropIndex('so_location_status_date_idx');
            $table->dropIndex('so_location_action_by_idx');
            $table->dropConstrainedForeignId('action_by_id');
            $table->dropColumn(['status', 'type', 'remarks', 'action_date']);
        });
    }
};
