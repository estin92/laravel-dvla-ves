<?php

namespace Estin92\DvlaVes\Tests\Feature;

use Estin92\DvlaVes\Facades\DvlaVes;
use Estin92\DvlaVes\Tests\TestCase;

class LookupVehicleCommandTest extends TestCase
{
    public function test_command_prints_vehicle_table_on_success(): void
    {
        DvlaVes::fake([
            'AB12CDE' => ['registrationNumber' => 'AB12CDE', 'make' => 'FORD', 'colour' => 'BLUE', 'fuelType' => 'PETROL'],
        ]);

        $this->artisan('dvla-ves:lookup', ['registration' => 'AB12CDE'])
            ->expectsOutputToContain('AB12CDE')
            ->expectsOutputToContain('FORD')
            ->assertExitCode(0);
    }

    public function test_command_displays_every_attribute_with_headline_labels(): void
    {
        DvlaVes::fake([
            'AB12CDE' => [
                'registrationNumber' => 'AB12CDE',
                'make' => 'FORD',
                'co2Emissions' => 119,
                'motStatus' => 'No details held by DVLA',
                'dateOfLastV5CIssued' => '2024-01-15',
                'taxStatus' => 'Taxed',
            ],
        ]);

        $this->artisan('dvla-ves:lookup', ['registration' => 'AB12CDE'])
            // headline labels with DVLA acronyms corrected
            ->expectsOutputToContain('Registration Number')
            ->expectsOutputToContain('CO2 Emissions')
            ->expectsOutputToContain('MOT Status')
            ->expectsOutputToContain('Date Of Last V5C Issued')
            ->expectsOutputToContain('Automated Vehicle')
            ->assertExitCode(0);
    }

    public function test_command_renders_values_for_each_type(): void
    {
        DvlaVes::fake([
            'AB12CDE' => [
                'registrationNumber' => 'AB12CDE',
                'taxDueDate' => '2026-12-01',     // date -> Y-m-d
                'markedForExport' => true,        // bool -> Yes
                'automatedVehicle' => false,      // bool -> No
                'fuelType' => 'ELECTRICITY',      // enum -> value
            ],
        ]);

        $this->artisan('dvla-ves:lookup', ['registration' => 'AB12CDE'])
            ->expectsOutputToContain('2026-12-01')
            ->expectsOutputToContain('Yes')
            ->expectsOutputToContain('No')
            ->expectsOutputToContain('ELECTRICITY')
            ->expectsOutputToContain('—') // null fields render as em dash
            ->assertExitCode(0);
    }

    public function test_command_reports_not_found(): void
    {
        DvlaVes::fake([]);

        $this->artisan('dvla-ves:lookup', ['registration' => 'NONE123'])
            ->assertExitCode(1);
    }

    public function test_command_reports_unconfigured(): void
    {
        config(['dvla-ves.api_key' => null]);
        $this->app->forgetInstance(\Estin92\DvlaVes\Contracts\VehicleEnquiry::class);
        $this->app->forgetInstance('dvla-ves');

        $this->artisan('dvla-ves:lookup', ['registration' => 'AB12CDE'])
            ->assertExitCode(1);
    }
}
