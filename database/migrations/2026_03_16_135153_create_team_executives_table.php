<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_executives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['team_id', 'user_id']);
        });

        // Seed existing single executive assignments into the new pivot table
        DB::table('teams')
            ->whereNotNull('operation_executive_id')
            ->get(['id', 'operation_executive_id'])
            ->each(function ($team) {
                DB::table('team_executives')->insertOrIgnore([
                    'team_id' => $team->id,
                    'user_id' => $team->operation_executive_id,
                    'is_primary' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_executives');
    }
};

