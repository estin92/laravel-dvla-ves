<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\Enums\EuroStatus;
use Estin92\DvlaVes\Tests\TestCase;

class EuroStatusTest extends TestCase
{
    public function test_from_api_returns_null_for_null_input(): void
    {
        $this->assertNull(EuroStatus::fromApi(null));
    }

    public function test_from_api_collapses_all_dvla_spellings_to_one_case(): void
    {
        $this->assertSame(EuroStatus::Euro6AG, EuroStatus::fromApi('Euro6 AG'));
        $this->assertSame(EuroStatus::Euro6AG, EuroStatus::fromApi('Euro 6 AG'));
        $this->assertSame(EuroStatus::Euro6AG, EuroStatus::fromApi('Euro VI AG'));
        $this->assertSame(EuroStatus::Euro6AG, EuroStatus::fromApi('EuroVI AG'));
        $this->assertSame(EuroStatus::Euro6AG, EuroStatus::fromApi('EURO 6 AG'));
    }

    public function test_from_api_parses_fully_uppercase_spelling_returned_by_live_api(): void
    {
        $this->assertSame(EuroStatus::Euro6AP, EuroStatus::fromApi('EURO 6 AP'));
    }

    public function test_from_api_parses_the_d_step_only_present_for_g_suffixes(): void
    {
        $this->assertSame(EuroStatus::Euro6DG, EuroStatus::fromApi('Euro6 DG'));
        $this->assertSame(EuroStatus::Euro6DG, EuroStatus::fromApi('Euro VI DG'));
    }

    public function test_from_api_parses_every_canonical_case(): void
    {
        foreach (EuroStatus::cases() as $case) {
            if ($case === EuroStatus::Unknown) {
                continue;
            }

            $this->assertSame($case, EuroStatus::fromApi($case->value));
        }
    }

    public function test_from_api_normalises_lowercase_and_extra_whitespace(): void
    {
        $this->assertSame(EuroStatus::Euro6BH, EuroStatus::fromApi('euro vi bh'));
        $this->assertSame(EuroStatus::Euro6BH, EuroStatus::fromApi('  Euro6   BH '));
    }

    public function test_from_api_coerces_unknown_values(): void
    {
        $this->assertSame(EuroStatus::Unknown, EuroStatus::fromApi('Euro5'));
        $this->assertSame(EuroStatus::Unknown, EuroStatus::fromApi('Euro6 ZZ'));
        $this->assertSame(EuroStatus::Unknown, EuroStatus::fromApi('not a euro status'));
    }

    public function test_case_values_are_unique(): void
    {
        $values = array_map(fn (EuroStatus $c) => $c->value, EuroStatus::cases());

        $this->assertCount(count($values), array_unique($values));
    }

    public function test_every_case_has_a_resolved_label(): void
    {
        foreach (EuroStatus::cases() as $case) {
            $label = $case->label();

            $this->assertNotSame('', $label);
            $this->assertStringNotContainsString('dvla-ves::enums', $label, "EuroStatus::{$case->name} has no translation");
        }
    }
}
