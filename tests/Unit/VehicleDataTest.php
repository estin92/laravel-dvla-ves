<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Carbon\CarbonImmutable;
use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Enums\FuelType;
use Estin92\DvlaVes\Enums\MotStatus;
use Estin92\DvlaVes\Enums\TaxStatus;
use Estin92\DvlaVes\Exceptions\DvlaVesException;
use Estin92\DvlaVes\Tests\TestCase;
use Illuminate\Support\Facades\Log;

class VehicleDataTest extends TestCase
{
    public function test_it_creates_vehicle_data_from_api_response(): void
    {
        $response = $this->getSampleApiResponse();

        $vehicleData = VehicleData::fromApiResponse($response);

        $this->assertSame('AA19AAA', $vehicleData->registrationNumber);
        $this->assertSame('FORD', $vehicleData->make);
        $this->assertSame('BLUE', $vehicleData->colour);
        $this->assertSame(FuelType::Petrol, $vehicleData->fuelType);
        $this->assertSame(1499, $vehicleData->engineCapacity);
        $this->assertSame(119, $vehicleData->co2Emissions);
        $this->assertSame(TaxStatus::Taxed, $vehicleData->taxStatus);
        $this->assertSame(MotStatus::Valid, $vehicleData->motStatus);
        $this->assertSame(2019, $vehicleData->yearOfManufacture);
        $this->assertSame('2019-03', $vehicleData->monthOfFirstRegistration);
        $this->assertSame('Euro 6', $vehicleData->euroStatus);
        $this->assertFalse($vehicleData->markedForExport);
    }

    public function test_it_handles_missing_optional_fields(): void
    {
        $response = [
            'registrationNumber' => 'AA19AAA',
        ];

        $vehicleData = VehicleData::fromApiResponse($response);

        $this->assertSame('AA19AAA', $vehicleData->registrationNumber);
        $this->assertNull($vehicleData->make);
        $this->assertNull($vehicleData->colour);
        $this->assertNull($vehicleData->fuelType);
        $this->assertNull($vehicleData->engineCapacity);
        $this->assertNull($vehicleData->taxStatus);
        $this->assertNull($vehicleData->motStatus);
    }

    public function test_it_stores_raw_response(): void
    {
        $response = $this->getSampleApiResponse();

        $vehicleData = VehicleData::fromApiResponse($response);

        $this->assertSame($response, $vehicleData->rawResponse);
    }

    public function test_get_first_registration_date_parses_correctly(): void
    {
        $response = [
            'registrationNumber' => 'AA19AAA',
            'monthOfFirstRegistration' => '2019-03',
        ];

        $vehicleData = VehicleData::fromApiResponse($response);

        $this->assertInstanceOf(CarbonImmutable::class, $vehicleData->getFirstRegistrationDate());
        $this->assertSame('2019-03-01', $vehicleData->getFirstRegistrationDate()->toDateString());
    }

    public function test_get_first_registration_date_returns_null_when_not_set(): void
    {
        $response = [
            'registrationNumber' => 'AA19AAA',
        ];

        $vehicleData = VehicleData::fromApiResponse($response);

        $this->assertNull($vehicleData->getFirstRegistrationDate());
    }

    public function test_month_fields_are_strings_by_default(): void
    {
        config(['dvla-ves.cast_year_month_only_fields_to_carbon' => false]);

        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'monthOfFirstRegistration' => '2019-03',
            'monthOfFirstDvlaRegistration' => '2019-04',
        ]);

        $this->assertSame('2019-03', $data->monthOfFirstRegistration);
        $this->assertSame('2019-04', $data->monthOfFirstDvlaRegistration);
    }

    public function test_month_fields_cast_to_carbon_immutable_when_enabled(): void
    {
        config(['dvla-ves.cast_year_month_only_fields_to_carbon' => true]);

        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'monthOfFirstRegistration' => '2019-03',
            'monthOfFirstDvlaRegistration' => '2019-04',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $data->monthOfFirstRegistration);
        $this->assertInstanceOf(CarbonImmutable::class, $data->monthOfFirstDvlaRegistration);
        $this->assertSame('2019-03-01', $data->monthOfFirstRegistration->toDateString());
        $this->assertSame('2019-04-01', $data->monthOfFirstDvlaRegistration->toDateString());
    }

    public function test_month_fields_stay_null_when_absent_even_if_cast_enabled(): void
    {
        config(['dvla-ves.cast_year_month_only_fields_to_carbon' => true]);

        $data = VehicleData::fromApiResponse(['registrationNumber' => 'AB12CDE']);

        $this->assertNull($data->monthOfFirstRegistration);
        $this->assertNull($data->monthOfFirstDvlaRegistration);
    }

    public function test_get_first_registration_date_works_regardless_of_cast_setting(): void
    {
        // getFirstRegistrationDate() must keep working whether the month field
        // is a string or already a CarbonImmutable.
        config(['dvla-ves.cast_year_month_only_fields_to_carbon' => true]);

        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'monthOfFirstRegistration' => '2019-03',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $data->getFirstRegistrationDate());
        $this->assertSame('2019-03-01', $data->getFirstRegistrationDate()->toDateString());
    }

    public function test_dates_are_carbon_immutable(): void
    {
        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'taxDueDate' => '2025-12-01',
            'motExpiryDate' => '2025-03-15',
            'artEndDate' => '2030-05-31',
            'dateOfLastV5CIssued' => '2024-01-15',
            'monthOfFirstRegistration' => '2019-03',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $data->taxDueDate);
        $this->assertInstanceOf(CarbonImmutable::class, $data->motExpiryDate);
        $this->assertInstanceOf(CarbonImmutable::class, $data->artEndDate);
        $this->assertInstanceOf(CarbonImmutable::class, $data->dateOfLastV5CIssued);
        $this->assertInstanceOf(CarbonImmutable::class, $data->getFirstRegistrationDate());
    }

    public function test_maps_month_of_first_dvla_registration(): void
    {
        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'monthOfFirstDvlaRegistration' => '2026-05',
        ]);

        $this->assertSame('2026-05', $data->monthOfFirstDvlaRegistration);
    }

    public function test_maps_automated_vehicle_flag(): void
    {
        $present = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'automatedVehicle' => false,
        ]);
        $absent = VehicleData::fromApiResponse(['registrationNumber' => 'AB12CDE']);

        $this->assertFalse($present->automatedVehicle);
        $this->assertFalse($present->isAutomatedVehicle());
        $this->assertNull($absent->automatedVehicle);
        $this->assertFalse($absent->isAutomatedVehicle());
    }

    public function test_fuel_tax_mot_export_helpers_are_null_safe(): void
    {
        $full = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'fuelType' => 'ELECTRICITY',
            'taxStatus' => 'SORN',
            'motStatus' => 'Valid',
            'markedForExport' => true,
        ]);
        $empty = VehicleData::fromApiResponse(['registrationNumber' => 'AB12CDE']);

        $hybrid = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'fuelType' => 'HYBRID ELECTRIC',
        ]);

        $this->assertTrue($full->isElectric());
        $this->assertFalse($full->isPetrol());
        $this->assertFalse($full->isDiesel());
        $this->assertFalse($full->isHybrid());
        $this->assertTrue($full->isSorn());
        $this->assertFalse($full->isTaxed());
        $this->assertFalse($full->isTaxDue()); // SORN is not "tax due"
        $this->assertTrue($full->hasValidMot());
        $this->assertTrue($full->isMarkedForExport());

        $this->assertTrue($hybrid->isHybrid());

        $this->assertFalse($empty->isElectric());
        $this->assertFalse($empty->isPetrol());
        $this->assertFalse($empty->isDiesel());
        $this->assertFalse($empty->isHybrid());
        $this->assertFalse($empty->isSorn());
        $this->assertFalse($empty->isTaxed());
        $this->assertFalse($empty->hasValidMot());
        $this->assertFalse($empty->isMarkedForExport());
        $this->assertFalse($empty->isAutomatedVehicle());
    }

    public function test_is_tax_due_is_true_only_for_untaxed_non_sorn(): void
    {
        $untaxed = VehicleData::fromApiResponse(['registrationNumber' => 'AB12CDE', 'taxStatus' => 'Untaxed']);
        $taxed = VehicleData::fromApiResponse(['registrationNumber' => 'AB12CDE', 'taxStatus' => 'Taxed']);
        $sorn = VehicleData::fromApiResponse(['registrationNumber' => 'AB12CDE', 'taxStatus' => 'SORN']);

        $this->assertTrue($untaxed->isTaxDue());
        $this->assertFalse($taxed->isTaxDue());
        $this->assertFalse($sorn->isTaxDue());
    }

    public function test_additional_rate_of_tax_helpers(): void
    {
        CarbonImmutable::setTestNow('2026-06-13');

        $future = VehicleData::fromApiResponse(['registrationNumber' => 'AB12CDE', 'artEndDate' => '2030-05-31']);
        $past = VehicleData::fromApiResponse(['registrationNumber' => 'AB12CDE', 'artEndDate' => '2020-05-31']);
        $none = VehicleData::fromApiResponse(['registrationNumber' => 'AB12CDE']);

        $this->assertInstanceOf(CarbonImmutable::class, $future->additionalRateOfTaxEndDate());
        $this->assertTrue($future->isSubjectToAdditionalRateOfTax());
        $this->assertFalse($past->isSubjectToAdditionalRateOfTax());
        $this->assertNull($none->additionalRateOfTaxEndDate());
        $this->assertFalse($none->isSubjectToAdditionalRateOfTax());

        CarbonImmutable::setTestNow();
    }

    public function test_missing_registration_number_throws_domain_exception(): void
    {
        $this->expectException(DvlaVesException::class);
        $this->expectExceptionMessage('missing registrationNumber');

        VehicleData::fromApiResponse(['make' => 'FORD']);
    }

    public function test_malformed_date_field_throws_domain_exception_not_carbon(): void
    {
        $this->expectException(DvlaVesException::class);

        VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'taxDueDate' => 'not-a-real-date',
        ]);
    }

    public function test_malformed_month_field_falls_back_to_raw_string_when_cast_enabled(): void
    {
        // A malformed YYYY-MM must NOT crash fromApiResponse. It falls back to
        // the raw string, preserving the data and matching the cast-off path.
        config(['dvla-ves.cast_year_month_only_fields_to_carbon' => true]);

        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'monthOfFirstRegistration' => 'garbage',
        ]);

        $this->assertSame('garbage', $data->monthOfFirstRegistration);
    }

    public function test_get_first_registration_date_returns_null_for_unparseable_month(): void
    {
        // getFirstRegistrationDate() is typed ?CarbonImmutable and must honour
        // that even when the underlying month string is junk — never throw.
        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'monthOfFirstRegistration' => 'garbage',
        ]);

        $this->assertNull($data->getFirstRegistrationDate());
    }

    public function test_unparseable_month_through_accessor_is_logged(): void
    {
        Log::spy();

        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'monthOfFirstRegistration' => 'garbage',
        ]);

        $this->assertNull($data->getFirstRegistrationDate());

        Log::shouldHaveReceived('debug')
            ->withArgs(fn (string $message) => str_contains($message, 'unparseable monthOfFirstRegistration'))
            ->once();
    }

    public function test_malformed_month_field_falls_back_to_raw_string_with_carbon_strict_mode_off(): void
    {
        // Apps may call Carbon::useStrictMode(false), which makes
        // createFromFormat() RETURN NULL instead of throwing. Without a null
        // guard the subsequent ->startOfMonth() fatals with an uncatchable
        // Error. Hydration must still fall back to the raw string here.
        config(['dvla-ves.cast_year_month_only_fields_to_carbon' => true]);
        CarbonImmutable::useStrictMode(false);

        try {
            $data = VehicleData::fromApiResponse([
                'registrationNumber' => 'AB12CDE',
                'monthOfFirstRegistration' => 'garbage',
            ]);

            $this->assertSame('garbage', $data->monthOfFirstRegistration);
        } finally {
            CarbonImmutable::useStrictMode(true);
        }
    }

    public function test_get_first_registration_date_returns_null_for_unparseable_month_with_strict_mode_off(): void
    {
        // The accessor casts on read; with strict mode off the same null-return
        // hazard applies. It must return null, never fatal.
        CarbonImmutable::useStrictMode(false);

        try {
            $data = VehicleData::fromApiResponse([
                'registrationNumber' => 'AB12CDE',
                'monthOfFirstRegistration' => 'garbage',
            ]);

            $this->assertNull($data->getFirstRegistrationDate());
        } finally {
            CarbonImmutable::useStrictMode(true);
        }
    }

    public function test_malformed_month_fallback_during_hydration_is_logged(): void
    {
        config(['dvla-ves.cast_year_month_only_fields_to_carbon' => true]);

        Log::spy();

        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'monthOfFirstRegistration' => 'garbage',
        ]);

        $this->assertSame('garbage', $data->monthOfFirstRegistration);

        Log::shouldHaveReceived('debug')
            ->withArgs(fn (string $message) => str_contains($message, 'unparseable month field'))
            ->atLeast()->once();
    }

    public function test_unrecognised_tax_status_coerces_to_null_and_logs(): void
    {
        Log::spy();

        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'taxStatus' => 'Some Future Status',
        ]);

        $this->assertNull($data->taxStatus);
        $this->assertFalse($data->isTaxed());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'unrecognised taxStatus'))
            ->once();
    }

    public function test_unrecognised_mot_status_coerces_to_null_and_logs(): void
    {
        Log::spy();

        $data = VehicleData::fromApiResponse([
            'registrationNumber' => 'AB12CDE',
            'motStatus' => 'Some Future Status',
        ]);

        $this->assertNull($data->motStatus);
        $this->assertFalse($data->hasValidMot());

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'unrecognised motStatus'))
            ->once();
    }

    /**
     * @return array<string, mixed>
     */
    private function getSampleApiResponse(): array
    {
        return [
            'registrationNumber' => 'AA19AAA',
            'make' => 'FORD',
            'colour' => 'BLUE',
            'fuelType' => 'PETROL',
            'engineCapacity' => 1499,
            'co2Emissions' => 119,
            'taxStatus' => 'Taxed',
            'taxDueDate' => '2025-12-01',
            'motStatus' => 'Valid',
            'motExpiryDate' => '2025-03-15',
            'yearOfManufacture' => 2019,
            'monthOfFirstRegistration' => '2019-03',
            'euroStatus' => 'Euro 6',
            'markedForExport' => false,
            'typeApproval' => 'M1',
            'wheelplan' => '2 AXLE RIGID BODY',
            'dateOfLastV5CIssued' => '2024-01-15',
        ];
    }
}
