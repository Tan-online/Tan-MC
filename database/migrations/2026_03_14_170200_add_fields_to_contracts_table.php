<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignId('client_id')->after('id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('location_id')->after('client_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('contract_no', 50)->after('location_id')->unique();
            $table->date('start_date')->after('contract_no');
            $table->date('end_date')->nullable()->after('start_date');
            $table->decimal('contract_value', 12, 2)->nullable()->after('end_date');
            $table->string('status', 30)->default('Active')->after('contract_value');
            $table->text('scope')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropUnique(['contract_no']);
            $table->dropColumn(['contract_no', 'start_date', 'end_date', 'contract_value', 'status', 'scope']);
        });
    }
};
