<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('code', 10)->after('name')->unique();
            $table->string('region')->nullable()->after('code');
            $table->boolean('is_active')->default(true)->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('states', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['name', 'code', 'region', 'is_active']);
        });
    }
};
