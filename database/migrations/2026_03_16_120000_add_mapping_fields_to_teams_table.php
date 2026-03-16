<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->foreignId('operation_executive_id')->nullable()->after('operation_area_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('manager_id')->nullable()->after('operation_executive_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('hod_id')->nullable()->after('manager_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('operation_executive_id');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('hod_id');
        });
    }
};
