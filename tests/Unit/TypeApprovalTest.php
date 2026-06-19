<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\Enums\TypeApproval;
use Estin92\DvlaVes\Tests\TestCase;

class TypeApprovalTest extends TestCase
{
    public function test_from_api_returns_null_for_null_input(): void
    {
        $this->assertNull(TypeApproval::fromApi(null));
    }

    public function test_from_api_returns_null_for_empty_or_whitespace_input(): void
    {
        $this->assertNull(TypeApproval::fromApi(''));
        $this->assertNull(TypeApproval::fromApi('   '));
    }

    public function test_from_api_parses_every_known_category_code(): void
    {
        $this->assertSame(TypeApproval::L1, TypeApproval::fromApi('L1'));
        $this->assertSame(TypeApproval::L7, TypeApproval::fromApi('L7'));
        $this->assertSame(TypeApproval::M1, TypeApproval::fromApi('M1'));
        $this->assertSame(TypeApproval::M1SP, TypeApproval::fromApi('M1SP'));
        $this->assertSame(TypeApproval::M3, TypeApproval::fromApi('M3'));
        $this->assertSame(TypeApproval::N1, TypeApproval::fromApi('N1'));
        $this->assertSame(TypeApproval::N3, TypeApproval::fromApi('N3'));
        $this->assertSame(TypeApproval::T1, TypeApproval::fromApi('T1'));
        $this->assertSame(TypeApproval::T3, TypeApproval::fromApi('T3'));
    }

    public function test_from_api_uppercases_and_trims_input(): void
    {
        $this->assertSame(TypeApproval::M1, TypeApproval::fromApi('m1'));
        $this->assertSame(TypeApproval::M1, TypeApproval::fromApi(' M1 '));
        $this->assertSame(TypeApproval::M1SP, TypeApproval::fromApi('m1sp'));
    }

    public function test_from_api_coerces_unknown_values(): void
    {
        $this->assertSame(TypeApproval::Unknown, TypeApproval::fromApi('Z9'));
        $this->assertSame(TypeApproval::Unknown, TypeApproval::fromApi('SOMETHING NEW'));
    }

    public function test_case_values_are_unique(): void
    {
        $values = array_map(fn (TypeApproval $c) => $c->value, TypeApproval::cases());

        $this->assertCount(count($values), array_unique($values));
    }
}
