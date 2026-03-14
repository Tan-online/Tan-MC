<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->foreignId('contract_id')->after('id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('location_id')->after('contract_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('team_id')->nullable()->after('location_id')->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->string('order_no', 50)->after('team_id')->unique();
            $table->date('requested_date')->after('order_no');
            $table->date('scheduled_date')->nullable()->after('requested_date');
            $table->string('status', 30)->default('Open')->after('scheduled_date');
            $table->string('priority', 20)->default('Medium')->after('status');
            $table->decimal('amount', 12, 2)->nullable()->after('priority');
            $table->text('remarks')->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('service_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('team_id');
            $table->dropUnique(['order_no']);
            $table->dropColumn(['order_no', 'requested_date', 'scheduled_date', 'status', 'priority', 'amount', 'remarks']);
        });
    }
};
