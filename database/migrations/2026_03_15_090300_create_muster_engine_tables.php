<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('muster_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->string('cycle_type', 20);
            $table->string('cycle_label', 60);
            $table->date('cycle_start_date');
            $table->date('cycle_end_date');
            $table->date('due_date');
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->unique(['contract_id', 'month', 'year']);
            $table->index(['year', 'month']);
            $table->index(['cycle_end_date', 'due_date']);
        });

        Schema::create('muster_expected', function (Blueprint $table) {
            $table->id();
            $table->foreignId('muster_cycle_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('executive_mapping_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('acted_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('status', 20)->default('Pending');
            $table->string('received_via', 20)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('last_action_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['muster_cycle_id', 'location_id']);
            $table->index(['contract_id', 'status']);
            $table->index(['location_id', 'status']);
        });

        Schema::create('muster_received', function (Blueprint $table) {
            $table->id();
            $table->foreignId('muster_expected_id')->constrained('muster_expected')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('action_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('status', 20);
            $table->string('receive_mode', 20)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['muster_expected_id', 'status']);
            $table->index(['received_at']);
        });

        Schema::create('executive_replacement_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('contract_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('old_executive_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('new_executive_id')->constrained('users')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('replaced_by_user_id')->nullable()->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->date('effective_date');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['client_id', 'contract_id', 'location_id', 'effective_date'], 'exec_replace_scope_effective_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executive_replacement_histories');
        Schema::dropIfExists('muster_received');
        Schema::dropIfExists('muster_expected');
        Schema::dropIfExists('muster_cycles');
    }
};
