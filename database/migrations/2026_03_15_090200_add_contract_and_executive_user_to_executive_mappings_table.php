<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executive_mappings', function (Blueprint $table) {
            $table->foreignId('contract_id')->nullable()->after('client_id')->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('executive_user_id')->nullable()->after('operation_area_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();

            $table->index(['client_id', 'contract_id', 'location_id', 'is_active'], 'exec_map_client_contract_location_active_idx');
        });
    }

    public function down(): void
    {
        Schema::table('executive_mappings', function (Blueprint $table) {
            $table->dropIndex('exec_map_client_contract_location_active_idx');
            $table->dropConstrainedForeignId('contract_id');
            $table->dropConstrainedForeignId('executive_user_id');
        });
    }
};
