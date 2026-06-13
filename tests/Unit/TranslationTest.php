<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\Enums\FuelType;
use Estin92\DvlaVes\Enums\MotStatus;
use Estin92\DvlaVes\Enums\TaxStatus;
use Estin92\DvlaVes\Tests\TestCase;
use Illuminate\Support\Facades\App;

class TranslationTest extends TestCase
{
    public function test_fuel_type_labels_resolve_in_english(): void
    {
        App::setLocale('en');

        $this->assertSame('Petrol', FuelType::Petrol->label());
        $this->assertSame('Diesel', FuelType::Diesel->label());
        $this->assertSame('Electric', FuelType::Electricity->label());
        $this->assertSame('Hybrid Electric', FuelType::HybridElectric->label());
        $this->assertSame('Electric/Diesel', FuelType::ElectricDiesel->label());
        $this->assertSame('Gas Bi-Fuel', FuelType::GasBiFuel->label());
        $this->assertSame('Other', FuelType::Other->label());
    }

    public function test_mot_status_labels_resolve_in_english(): void
    {
        App::setLocale('en');

        $this->assertSame('Valid', MotStatus::Valid->label());
        $this->assertSame('Not Valid', MotStatus::NotValid->label());
        $this->assertSame('No Details Held by DVLA', MotStatus::NoDetailsHeld->label());
        $this->assertSame('No Results Returned', MotStatus::NoResultsReturned->label());
    }

    public function test_tax_status_labels_resolve_in_english(): void
    {
        App::setLocale('en');

        $this->assertSame('Taxed', TaxStatus::Taxed->label());
        $this->assertSame('Untaxed', TaxStatus::Untaxed->label());
        $this->assertSame('SORN (Statutory Off Road Notification)', TaxStatus::Sorn->label());
        $this->assertSame('Not Taxed for on Road Use', TaxStatus::NotTaxable->label());
    }

    public function test_every_enum_case_resolves_without_leaking_keys(): void
    {
        App::setLocale('en');

        foreach ([...FuelType::cases(), ...MotStatus::cases(), ...TaxStatus::cases()] as $case) {
            $label = $case->label();

            $this->assertNotEmpty($label);
            $this->assertStringNotContainsString('dvla-ves::', $label, "{$case->name} key not resolved");
        }
    }
}
