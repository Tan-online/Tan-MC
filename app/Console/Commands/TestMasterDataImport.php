<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\OperationArea;
use App\Models\State;
use Illuminate\Console\Command;

class TestMasterDataImport extends Command
{
    protected $signature = 'test:master-data-import {type=locations}';
    protected $description = 'Test master data import setup and dependencies';

    public function handle(): int
    {
        $type = $this->argument('type');

        $this->info("Testing {$type} import prerequisites...\n");

        match ($type) {
            'locations' => $this->testLocations(),
            'contracts' => $this->testContracts(),
            'clients' => $this->testClients(),
            'service-orders' => $this->testServiceOrders(),
            default => $this->error("Unknown type: {$type}"),
        };

        return 0;
    }

    private function testLocations(): void
    {
        $this->info("1. Checking Clients...");
        $clients = Client::query()->count();
        if ($clients === 0) {
            $this->error("   ❌ No clients found. Please add at least one client.");
        } else {
            $this->line("   ✓ Found {$clients} clients");
            Client::query()->limit(3)->each(fn ($c) => 
                $this->line("     - {$c->code}: {$c->name}")
            );
        }

        $this->info("\n2. Checking States...");
        $states = State::query()->count();
        if ($states === 0) {
            $this->error("   ❌ No states found. Please add states first.");
        } else {
            $this->line("   ✓ Found {$states} states");
            State::query()->limit(3)->each(fn ($s) => 
                $this->line("     - {$s->code}: {$s->name}")
            );
        }

        $this->info("\n3. Checking Operation Areas...");
        $areas = OperationArea::query()->count();
        if ($areas === 0) {
            $this->error("   ❌ No operation areas found. Please add operation areas for each state.");
        } else {
            $this->line("   ✓ Found {$areas} operation areas");
            OperationArea::query()->with('state')->limit(3)->each(fn ($a) => 
                $this->line("     - {$a->name} (State: {$a->state?->code})")
            );
        }

        $this->info("\n4. Location Import Ready!");
        $this->info("Template requires: client_code, state_code, code, name, address, is_active");
    }

    private function testContracts(): void
    {
        $this->warn("Contract import testing not yet implemented.");
    }

    private function testClients(): void
    {
        $this->warn("Client import testing not yet implemented.");
    }

    private function testServiceOrders(): void
    {
        $this->warn("Service orders import testing not yet implemented.");
    }
}
