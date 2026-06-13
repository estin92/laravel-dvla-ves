<?php

namespace Estin92\DvlaVes\Tests\Unit;

use Estin92\DvlaVes\Enums\MotStatus;
use Estin92\DvlaVes\Tests\TestCase;

class MotStatusTest extends TestCase
{
    public function test_from_api_maps_every_documented_wire_value(): void
    {
        // Values confirmed against the official VES v1.2.0 spec and real captures.
        $this->assertSame(MotStatus::Valid, MotStatus::fromApi('Valid'));
        $this->assertSame(MotStatus::NotValid, MotStatus::fromApi('Not valid'));
        $this->assertSame(MotStatus::NoDetailsHeld, MotStatus::fromApi('No details held by DVLA'));
        $this->assertSame(MotStatus::NoResultsReturned, MotStatus::fromApi('No results returned'));
    }

    public function test_from_api_returns_null_for_null_and_unknown(): void
    {
        $this->assertNull(MotStatus::fromApi(null));
        $this->assertNull(MotStatus::fromApi('Unknown'));
    }

    public function test_is_valid(): void
    {
        $this->assertTrue(MotStatus::Valid->isValid());
        $this->assertFalse(MotStatus::NotValid->isValid());
    }
}
