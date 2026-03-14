<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('client_id')->after('id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('state_id')->after('client_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('operation_area_id')->after('state_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('name')->after('operation_area_id');
            $table->string('city')->nullable()->after('name');
            $table->text('address')->nullable()->after('city');
            $table->string('postal_code', 20)->nullable()->after('address');
            $table->boolean('is_active')->default(true)->after('postal_code');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('state_id');
            $table->dropConstrainedForeignId('operation_area_id');
            $table->dropColumn(['name', 'city', 'address', 'postal_code', 'is_active']);
        });
    }
};
