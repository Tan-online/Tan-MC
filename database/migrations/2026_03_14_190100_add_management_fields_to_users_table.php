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
            $table->string('phone', 20)->nullable()->after('email');
            $table->foreignId('department_id')->nullable()->after('phone')->constrained()->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('role_id')->nullable()->after('department_id')->constrained()->restrictOnDelete()->cascadeOnUpdate();
            $table->foreignId('manager_id')->nullable()->after('role_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreignId('hod_id')->nullable()->after('manager_id')->constrained('users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('status', 20)->default('Active')->after('hod_id');
        });

        DB::table('users')
            ->orderBy('id')
            ->get(['id', 'employee_code'])
            ->each(function (object $user): void {
                $code = preg_replace('/\D+/', '', (string) $user->employee_code);
                $normalizedCode = str_pad((string) ($code ?: $user->id), 6, '0', STR_PAD_LEFT);

                DB::table('users')
                    ->where('id', $user->id)
                    ->update([
                        'employee_code' => substr($normalizedCode, -6),
                        'role_id' => $user->id === 1 ? 1 : 4,
                        'status' => 'Active',
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('department_id');
            $table->dropConstrainedForeignId('role_id');
            $table->dropConstrainedForeignId('manager_id');
            $table->dropConstrainedForeignId('hod_id');
            $table->dropColumn(['phone', 'status']);
        });
    }
};
