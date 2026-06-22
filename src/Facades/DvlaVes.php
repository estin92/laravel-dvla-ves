<?php

namespace Estin92\DvlaVes\Facades;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Testing\FakeVehicleEnquiryService;
use Illuminate\Support\Facades\Facade;
use Throwable;

/**
 * @method static VehicleData lookup(string $registration)
 * @method static bool isConfigured()
 *
 * @see VehicleEnquiry
 */
class DvlaVes extends Facade
{
    /**
     * Swap the container binding for a fake, for testing.
     *
     * @param  array<string, array<string, mixed>|VehicleData|Throwable>  $responses
     */
    public static function fake(array $responses = []): FakeVehicleEnquiryService
    {
        $fake = new FakeVehicleEnquiryService($responses);

        static::$app->instance(VehicleEnquiry::class, $fake);
        static::$app->instance('dvla-ves', $fake);

        return $fake;
    }

    protected static function getFacadeAccessor(): string
    {
        return 'dvla-ves';
    }
}
