<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_code', 50)->nullable()->after('name');
        });

        DB::table('users')
            ->select('id')
            ->whereNull('employee_code')
            ->orderBy('id')
            ->get()
            ->each(function (object $user): void {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'employee_code' => 'EMP' . str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
                    ]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('employee_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['employee_code']);
            $table->dropColumn('employee_code');
        });
    }
};
