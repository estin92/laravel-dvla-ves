<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\Enums\TaxStatus;
use Estin92\DvlaVes\Tests\TestCase;

class TaxStatusTest extends TestCase
{
    public function test_from_api_maps_every_documented_wire_value(): void
    {
        $this->assertSame(TaxStatus::Taxed, TaxStatus::fromApi('Taxed'));
        $this->assertSame(TaxStatus::Untaxed, TaxStatus::fromApi('Untaxed'));
        $this->assertSame(TaxStatus::Sorn, TaxStatus::fromApi('SORN'));
        $this->assertSame(TaxStatus::NotTaxable, TaxStatus::fromApi('Not Taxed for on Road Use'));
    }

    public function test_from_api_returns_null_for_null_and_unknown(): void
    {
        $this->assertNull(TaxStatus::fromApi(null));
        $this->assertNull(TaxStatus::fromApi('Something Unseen'));
    }

    public function test_is_taxed_and_is_sorn(): void
    {
        $this->assertTrue(TaxStatus::Taxed->isTaxed());
        $this->assertFalse(TaxStatus::Untaxed->isTaxed());
        $this->assertTrue(TaxStatus::Sorn->isSorn());
        $this->assertFalse(TaxStatus::Taxed->isSorn());
    }
}
