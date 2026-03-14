<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operation_areas', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('code', 20)->nullable()->unique()->after('name');
            $table->foreignId('state_id')->after('code')->constrained()->cascadeOnUpdate()->restrictOnDelete();
            $table->text('description')->nullable()->after('state_id');
            $table->boolean('is_active')->default(true)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('operation_areas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('state_id');
            $table->dropUnique(['code']);
            $table->dropColumn(['name', 'code', 'description', 'is_active']);
        });
    }
};
