<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('code', 20)->nullable()->unique()->after('name');
            $table->foreignId('department_id')->after('code')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('operation_area_id')->after('department_id')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->string('lead_name')->nullable()->after('operation_area_id');
            $table->unsignedInteger('members_count')->default(0)->after('lead_name');
            $table->boolean('is_active')->default(true)->after('members_count');
        });
    }

    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('operation_area_id');
            $table->dropUnique(['code']);
            $table->dropColumn(['name', 'code', 'lead_name', 'members_count', 'is_active']);
        });
    }
};
