<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('name')->after('id');
            $table->string('code', 20)->nullable()->unique()->after('name');
            $table->string('contact_person')->nullable()->after('code');
            $table->string('email')->nullable()->after('contact_person');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('industry')->nullable()->after('phone');
            $table->boolean('is_active')->default(true)->after('industry');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['name', 'code', 'contact_person', 'email', 'phone', 'industry', 'is_active']);
        });
    }
};
