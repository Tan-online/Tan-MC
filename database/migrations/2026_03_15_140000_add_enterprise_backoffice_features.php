<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('activity_logs', 'ip_address')) {
                $table->string('ip_address', 45)->nullable()->after('description');
                $table->index('ip_address', 'activity_logs_ip_address_idx');
            }
        });

        Schema::create('audit_trails', function (Blueprint $table) {
            $table->id();
            $table->string('module', 80);
            $table->string('event', 40);
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->timestamp('changed_at')->useCurrent();

            $table->index(['module', 'changed_at'], 'audit_trails_module_changed_at_idx');
            $table->index(['auditable_type', 'auditable_id'], 'audit_trails_auditable_idx');
        });

        Schema::create('generated_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->string('category', 40);
            $table->string('type', 80);
            $table->string('format', 20);
            $table->string('status', 20)->default('pending');
            $table->string('disk', 40)->default('local');
            $table->string('path')->nullable();
            $table->string('file_name')->nullable();
            $table->json('filters')->nullable();
            $table->unsignedInteger('record_count')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'generated_exports_user_status_idx');
            $table->index(['category', 'type'], 'generated_exports_category_type_idx');
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->string('type', 80);
            $table->string('status', 20)->default('pending');
            $table->string('disk', 40)->default('local');
            $table->string('stored_path');
            $table->string('original_file_name');
            $table->unsignedInteger('inserted_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('failure_report')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'import_batches_user_status_idx');
            $table->index(['type', 'status'], 'import_batches_type_status_idx');
        });

        Schema::table('service_orders', function (Blueprint $table) {
            $table->index(['contract_id', 'status'], 'service_orders_contract_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropIndex('service_orders_contract_status_idx');
        });

        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('generated_exports');
        Schema::dropIfExists('audit_trails');

        Schema::table('activity_logs', function (Blueprint $table) {
            if (Schema::hasColumn('activity_logs', 'ip_address')) {
                $table->dropIndex('activity_logs_ip_address_idx');
                $table->dropColumn('ip_address');
            }
        });
    }
};