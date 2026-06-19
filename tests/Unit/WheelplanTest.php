<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\Enums\Wheelplan;
use Estin92\DvlaVes\Tests\TestCase;

class WheelplanTest extends TestCase
{
    public function test_from_api_returns_null_for_null_input(): void
    {
        $this->assertNull(Wheelplan::fromApi(null));
    }

    public function test_from_api_returns_null_for_empty_or_whitespace_input(): void
    {
        $this->assertNull(Wheelplan::fromApi(''));
        $this->assertNull(Wheelplan::fromApi('   '));
    }

    public function test_from_api_parses_every_known_wire_value(): void
    {
        foreach (Wheelplan::cases() as $case) {
            if ($case === Wheelplan::Unknown) {
                continue;
            }

            $this->assertSame($case, Wheelplan::fromApi($case->value));
        }
    }

    public function test_from_api_parses_value_from_real_fixture(): void
    {
        $this->assertSame(Wheelplan::TwoAxleRigidBody, Wheelplan::fromApi('2 AXLE RIGID BODY'));
    }

    public function test_from_api_normalises_casing_and_extra_whitespace(): void
    {
        $this->assertSame(Wheelplan::TwoAxleRigidBody, Wheelplan::fromApi('2 axle rigid body'));
        $this->assertSame(Wheelplan::TwoAxleRigidBody, Wheelplan::fromApi('  2   AXLE  RIGID   BODY '));
        $this->assertSame(Wheelplan::TwoAxleArtic, Wheelplan::fromApi('2 axle + artic'));
    }

    public function test_from_api_coerces_unknown_values(): void
    {
        $this->assertSame(Wheelplan::Unknown, Wheelplan::fromApi('5 AXLE SPACESHIP'));
    }

    public function test_case_values_are_unique(): void
    {
        $values = array_map(fn (Wheelplan $c) => $c->value, Wheelplan::cases());

        $this->assertCount(count($values), array_unique($values));
    }
}
