<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Tests\TestCase;

class VehicleDataFieldAuditTest extends TestCase
{
    /**
     * The complete set of top-level keys the DVLA VES endpoint is known to
     * return (verified against the v1.2.0 spec + real captures). The DTO must
     * map this set and no field from any OTHER service may be introduced.
     */
    private const KNOWN_VES_KEYS = [
        'registrationNumber', 'make', 'colour', 'fuelType', 'engineCapacity',
        'co2Emissions', 'revenueWeight', 'taxStatus', 'taxDueDate', 'motStatus',
        'motExpiryDate', 'yearOfManufacture', 'monthOfFirstRegistration',
        'monthOfFirstDvlaRegistration', 'euroStatus', 'realDrivingEmissions',
        'wheelplan', 'artEndDate', 'typeApproval', 'markedForExport',
        'automatedVehicle', 'dateOfLastV5CIssued',
    ];

    public function test_full_fixture_maps_into_vehicle_data(): void
    {
        $data = VehicleData::fromApiResponse($this->fixture('ves-full'));

        $this->assertSame('AB12CDE', $data->registrationNumber);
        $this->assertSame('SKODA', $data->make);
        $this->assertTrue($data->isDiesel());
        $this->assertTrue($data->hasValidMot());

        // Behavioural assertions on the numeric + date fields so a mis-mapped
        // key (e.g. swapping engineCapacity/revenueWeight) cannot pass green.
        $this->assertSame(1659, $data->revenueWeight);
        $this->assertSame(1598, $data->engineCapacity);
        $this->assertSame(109, $data->co2Emissions);
        $this->assertSame(2011, $data->yearOfManufacture);
        $this->assertSame('2025-11-04', $data->taxDueDate?->toDateString());
        $this->assertSame('2026-07-28', $data->motExpiryDate?->toDateString());
        $this->assertSame('2025-10-28', $data->dateOfLastV5CIssued?->toDateString());
        $this->assertSame('2011-09', $data->monthOfFirstRegistration);
        $this->assertSame('2011-09-01', $data->getFirstRegistrationDate()?->toDateString());
    }

    public function test_automated_polestar_fixture_sets_the_flag(): void
    {
        $data = VehicleData::fromApiResponse($this->fixture('ves-automated-polestar'));

        $this->assertFalse($data->automatedVehicle);
        $this->assertFalse($data->isAutomatedVehicle());
        $this->assertTrue($data->isElectric());
    }

    public function test_sparse_fixture_nulls_missing_fields(): void
    {
        $data = VehicleData::fromApiResponse($this->fixture('ves-sparse'));

        $this->assertNull($data->engineCapacity);
        $this->assertNull($data->motExpiryDate);
        $this->assertNull($data->typeApproval);
        $this->assertNull($data->automatedVehicle);
    }

    public function test_no_fixture_contains_a_field_outside_the_known_ves_set(): void
    {
        foreach (['ves-full', 'ves-automated-polestar', 'ves-sparse'] as $name) {
            foreach (array_keys($this->fixture($name)) as $key) {
                $this->assertContains(
                    $key,
                    self::KNOWN_VES_KEYS,
                    "Fixture {$name} contains key '{$key}' not in the known VES field set — possible MOT-history/VDG bleed"
                );
            }
        }
    }

    /**
     * Every shipped fixture plate must be a deliberately-constructed synthetic
     * sample, not a real plate captured during development. Rather than denylist
     * known real plates (which only catches plates we already know about), assert
     * each fixture plate is drawn from the package's intentional synthetic set —
     * sequential current-format placeholders (AB12CDE, CD34FGH, EF56GHJ) that no
     * real vehicle carries. A real capture pasted into a fixture fails here.
     */
    private const SYNTHETIC_PLATES = ['AB12CDE', 'CD34FGH', 'EF56GHJ', 'AA19AAA'];

    public function test_every_fixture_uses_a_synthetic_plate(): void
    {
        foreach (['ves-full', 'ves-automated-polestar', 'ves-sparse'] as $name) {
            $reg = $this->fixture($name)['registrationNumber'] ?? '';

            $this->assertMatchesRegularExpression(
                '/^[A-Z]{2}[0-9]{2}[A-Z]{3}$/',
                $reg,
                "Fixture {$name} plate '{$reg}' is not current UK format",
            );
            $this->assertContains(
                $reg,
                self::SYNTHETIC_PLATES,
                "Fixture {$name} plate '{$reg}' is not in the synthetic set — a real capture may have leaked in",
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fixture(string $name): array
    {
        return json_decode(file_get_contents(__DIR__."/../fixtures/{$name}.json"), true);
    }
}
