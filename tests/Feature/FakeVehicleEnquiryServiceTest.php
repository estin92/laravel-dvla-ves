<?php

namespace Estin92\DvlaVes\Tests\Feature;

use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Exceptions\VehicleNotFoundException;
use Estin92\DvlaVes\Facades\DvlaVes;
use Estin92\DvlaVes\Tests\TestCase;

class FakeVehicleEnquiryServiceTest extends TestCase
{
    public function test_fake_returns_canned_vehicle_data_by_registration(): void
    {
        DvlaVes::fake([
            'AB12CDE' => ['registrationNumber' => 'AB12CDE', 'make' => 'TESLA', 'fuelType' => 'ELECTRICITY'],
        ]);

        $result = DvlaVes::lookup('AB12CDE');

        $this->assertInstanceOf(VehicleData::class, $result);
        $this->assertSame('TESLA', $result->make);
        $this->assertTrue($result->isElectric());
    }

    public function test_fake_normalises_registration_for_matching(): void
    {
        DvlaVes::fake([
            'AB12CDE' => ['registrationNumber' => 'AB12CDE', 'make' => 'TESLA'],
        ]);

        $this->assertSame('TESLA', DvlaVes::lookup('ab 12 cde')->make);
    }

    public function test_fake_throws_configured_exception(): void
    {
        DvlaVes::fake([
            'NONE123' => new VehicleNotFoundException('NONE123'),
        ]);

        $this->expectException(VehicleNotFoundException::class);

        DvlaVes::lookup('NONE123');
    }

    public function test_fake_throws_not_found_for_unconfigured_registration(): void
    {
        DvlaVes::fake([]);

        $this->expectException(VehicleNotFoundException::class);

        DvlaVes::lookup('UNKNOWN');
    }

    public function test_fake_is_configured_returns_true(): void
    {
        DvlaVes::fake([]);

        $this->assertTrue(DvlaVes::isConfigured());
    }
}
