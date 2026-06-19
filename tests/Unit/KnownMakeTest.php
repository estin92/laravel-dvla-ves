<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\Support\KnownMake;
use Estin92\DvlaVes\Tests\TestCase;
use Illuminate\Support\Str;

class KnownMakeTest extends TestCase
{
    public function test_all_returns_a_non_empty_list_of_strings(): void
    {
        $makes = KnownMake::all();

        $this->assertNotEmpty($makes);
        $this->assertContainsOnlyString($makes);
    }

    public function test_makes_are_unique_case_insensitively(): void
    {
        $makes = KnownMake::all();

        $lowered = array_map(fn (string $make) => Str::lower(trim($make)), $makes);

        $this->assertCount(count($makes), array_unique($lowered), 'Two makes resolve to the same canonical key');
    }

    public function test_is_known_matches_an_exact_dvla_value(): void
    {
        $this->assertTrue(KnownMake::isKnown('FORD'));
        $this->assertTrue(KnownMake::isKnown('LAND ROVER'));
        $this->assertTrue(KnownMake::isKnown('AC (ELECTRIC)'));
    }

    public function test_is_known_is_case_insensitive_and_trims(): void
    {
        $this->assertTrue(KnownMake::isKnown('ford'));
        $this->assertTrue(KnownMake::isKnown('  Land Rover  '));
        $this->assertTrue(KnownMake::isKnown('lynk & co'));
    }

    public function test_is_known_rejects_unknown_and_empty_values(): void
    {
        $this->assertFalse(KnownMake::isKnown('NOT A REAL MAKE'));
        $this->assertFalse(KnownMake::isKnown(''));
        $this->assertFalse(KnownMake::isKnown('   '));
        $this->assertFalse(KnownMake::isKnown(null));
    }

    public function test_canonical_returns_the_dvla_spelling_for_a_loose_match(): void
    {
        $this->assertSame('FORD', KnownMake::canonical('ford'));
        $this->assertSame('LAND ROVER', KnownMake::canonical('  land rover '));
        $this->assertSame('10Ten', KnownMake::canonical('10TEN'));
    }

    public function test_canonical_returns_null_for_unknown_values(): void
    {
        $this->assertNull(KnownMake::canonical('NOT A REAL MAKE'));
        $this->assertNull(KnownMake::canonical(null));
    }
}
