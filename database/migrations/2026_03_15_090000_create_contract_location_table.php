<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_location', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contract_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contract_id', 'location_id']);
            $table->index('location_id');
        });

        $timestamp = now();

        DB::table('contracts')
            ->select('id', 'location_id')
            ->whereNotNull('location_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $contract) use ($timestamp): void {
                DB::table('contract_location')->updateOrInsert(
                    [
                        'contract_id' => $contract->id,
                        'location_id' => $contract->location_id,
                    ],
                    [
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_location');
    }
};
