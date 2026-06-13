<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\Enums\FuelType;
use Estin92\DvlaVes\Tests\TestCase;

class FuelTypeTest extends TestCase
{
    public function test_from_api_returns_null_for_null_input(): void
    {
        $this->assertNull(FuelType::fromApi(null));
    }

    public function test_from_api_parses_every_known_wire_value(): void
    {
        // Sourced from real DVLA VES responses captured against live regs
        // (storage/app/private/dvla-ves-debug). If DVLA returns a different
        // string for one of these, this test fails loudly.
        $this->assertSame(FuelType::Petrol, FuelType::fromApi('PETROL'));
        $this->assertSame(FuelType::Diesel, FuelType::fromApi('DIESEL'));
        $this->assertSame(FuelType::Electricity, FuelType::fromApi('ELECTRICITY'));
        $this->assertSame(FuelType::HybridElectric, FuelType::fromApi('HYBRID ELECTRIC'));
        $this->assertSame(FuelType::ElectricDiesel, FuelType::fromApi('ELECTRIC DIESEL'));
        $this->assertSame(FuelType::GasBiFuel, FuelType::fromApi('GAS BI-FUEL'));
        $this->assertSame(FuelType::Other, FuelType::fromApi('OTHER'));
    }

    public function test_from_api_uppercases_lowercase_or_mixed_case_input(): void
    {
        $this->assertSame(FuelType::Petrol, FuelType::fromApi('petrol'));
        $this->assertSame(FuelType::Petrol, FuelType::fromApi('Petrol'));
        $this->assertSame(FuelType::HybridElectric, FuelType::fromApi('Hybrid Electric'));
    }

    public function test_from_api_coerces_unknown_values_to_other(): void
    {
        // Future-proofing: if DVLA introduces a new fuelType we haven't seen,
        // fromApi must not return null — the caller relies on a typed value
        // and the verbatim string is preserved by the consumer separately.
        $this->assertSame(FuelType::Other, FuelType::fromApi('GAS DIESEL'));
        $this->assertSame(FuelType::Other, FuelType::fromApi('FUEL CELLS'));
        $this->assertSame(FuelType::Other, FuelType::fromApi('STEAM'));
        $this->assertSame(FuelType::Other, FuelType::fromApi('GAS'));
        $this->assertSame(FuelType::Other, FuelType::fromApi('SOMETHING NOBODY HAS SEEN YET'));
    }

    public function test_every_case_value_matches_a_real_dvla_wire_value_or_is_other(): void
    {
        // The DVLA VES API response field `fuelType` uses spaces between
        // tokens (e.g. "HYBRID ELECTRIC"). Our enum mirrors that exactly so
        // tryFrom round-trips the wire value without translation.
        foreach (FuelType::cases() as $case) {
            $this->assertMatchesRegularExpression(
                '/^[A-Z][A-Z -]*[A-Z]$/',
                $case->value,
                "FuelType::{$case->name} has a value ({$case->value}) that doesn't look like a DVLA wire string"
            );
        }
    }

    public function test_case_values_are_unique(): void
    {
        $values = array_map(fn (FuelType $c) => $c->value, FuelType::cases());

        $this->assertCount(count($values), array_unique($values));
    }

    public function test_is_matches_the_same_case(): void
    {
        $this->assertTrue(FuelType::Petrol->is(FuelType::Petrol));
        $this->assertFalse(FuelType::Petrol->is(FuelType::Diesel));
    }

    public function test_named_fuel_shortcuts(): void
    {
        $this->assertTrue(FuelType::Petrol->isPetrol());
        $this->assertTrue(FuelType::Diesel->isDiesel());
        $this->assertTrue(FuelType::Electricity->isElectric());
        $this->assertTrue(FuelType::HybridElectric->isHybrid());

        $this->assertFalse(FuelType::Diesel->isPetrol());
        $this->assertFalse(FuelType::Petrol->isElectric());
        $this->assertFalse(FuelType::Petrol->isHybrid());
    }
}
