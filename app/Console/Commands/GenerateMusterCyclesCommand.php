<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Services\MusterComplianceService;
use Illuminate\Console\Command;

class GenerateMusterCyclesCommand extends Command
{
    protected $signature = 'muster:generate {month?} {year?}';

    protected $description = 'Generate muster cycles for active contracts and service orders.';

    public function handle(MusterComplianceService $musterComplianceService): int
    {
        $month = (int) ($this->argument('month') ?: now()->format('n'));
        $year = (int) ($this->argument('year') ?: now()->format('Y'));
        $generated = 0;

        Contract::query()
            ->where('status', 'Active')
            ->with('locations:id')
            ->chunkById(100, function ($contracts) use ($musterComplianceService, $month, $year, &$generated): void {
                foreach ($contracts as $contract) {
                    if ($musterComplianceService->ensureCycleForContractMonth($contract, $month, $year)) {
                        $generated++;
                    }
                }
            });

        $this->info("Muster generation completed for {$month}/{$year}. Cycles processed: {$generated}.");

        return self::SUCCESS;
    }
}
