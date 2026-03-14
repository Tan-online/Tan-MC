<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executive_mappings', function (Blueprint $table) {
            $table->foreignId('client_id')->after('id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->after('client_id')->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('operation_area_id')->after('location_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('executive_name')->after('operation_area_id');
            $table->string('designation')->nullable()->after('executive_name');
            $table->string('email')->nullable()->after('designation');
            $table->string('phone', 30)->nullable()->after('email');
            $table->boolean('is_primary')->default(false)->after('phone');
            $table->boolean('is_active')->default(true)->after('is_primary');
        });
    }

    public function down(): void
    {
        Schema::table('executive_mappings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropConstrainedForeignId('operation_area_id');
            $table->dropColumn(['executive_name', 'designation', 'email', 'phone', 'is_primary', 'is_active']);
        });
    }
};
