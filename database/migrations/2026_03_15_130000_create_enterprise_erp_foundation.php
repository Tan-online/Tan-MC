<?php

use Database\Seeders\EnterpriseRbacSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('roles', 'description')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('description', 500)->nullable()->after('slug');
            });
        }

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('module', 80);
            $table->string('action', 80);
            $table->string('description', 500)->nullable();
            $table->timestamps();

            $table->unique(['module', 'action']);
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();

            $table->unique(['user_id', 'role_id']);
        });

        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('code', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedInteger('step_order');
            $table->foreignId('role_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('action', 80);

            $table->unique(['workflow_id', 'step_order']);
        });

        Schema::create('workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('workflow_step_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->morphs('actionable');
            $table->string('action', 80);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['action', 'created_at']);
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->string('module', 80);
            $table->string('action', 80);
            $table->unsignedBigInteger('record_id')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['module', 'action']);
            $table->index(['record_id', 'created_at']);
        });

        Schema::create('dispatch_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_order_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('dispatched_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('status', 30)->default('pending');
            $table->timestamp('dispatched_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['service_order_id', 'status']);
            $table->index(['status', 'dispatched_at']);
        });

        if (! Schema::hasColumn('muster_expected', 'final_closed_at') || ! Schema::hasColumn('muster_expected', 'final_closed_by_user_id')) {
            Schema::table('muster_expected', function (Blueprint $table) {
                if (! Schema::hasColumn('muster_expected', 'final_closed_at')) {
                    $table->timestamp('final_closed_at')->nullable()->after('approved_at');
                }

                if (! Schema::hasColumn('muster_expected', 'final_closed_by_user_id')) {
                    $table->foreignId('final_closed_by_user_id')->nullable()->after('final_closed_at')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
                }
            });
        }

        app(EnterpriseRbacSeeder::class)->run();
    }

    public function down(): void
    {
        if (Schema::hasColumn('muster_expected', 'final_closed_by_user_id') || Schema::hasColumn('muster_expected', 'final_closed_at')) {
            Schema::table('muster_expected', function (Blueprint $table) {
                if (Schema::hasColumn('muster_expected', 'final_closed_by_user_id')) {
                    $table->dropConstrainedForeignId('final_closed_by_user_id');
                }

                if (Schema::hasColumn('muster_expected', 'final_closed_at')) {
                    $table->dropColumn('final_closed_at');
                }
            });
        }

        Schema::dropIfExists('dispatch_entries');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('workflow_actions');
        Schema::dropIfExists('workflow_steps');
        Schema::dropIfExists('workflows');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');

        if (Schema::hasColumn('roles', 'description')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
