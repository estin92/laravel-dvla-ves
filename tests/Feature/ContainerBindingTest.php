<?php

namespace Estin92\DvlaVes\Tests\Feature;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Services\CachingVehicleEnquiryService;
use Estin92\DvlaVes\Services\VehicleEnquiryService;
use Estin92\DvlaVes\Tests\TestCase;

class ContainerBindingTest extends TestCase
{
    public function test_interface_resolves_to_the_real_service_by_default(): void
    {
        $this->assertInstanceOf(VehicleEnquiryService::class, $this->app->make(VehicleEnquiry::class));
    }

    public function test_alias_resolves_to_the_interface_binding(): void
    {
        $this->assertInstanceOf(VehicleEnquiry::class, $this->app->make('dvla-ves'));
    }

    public function test_interface_resolves_to_caching_decorator_when_enabled(): void
    {
        config(['dvla-ves.cache.enabled' => true]);
        $this->app->forgetInstance(VehicleEnquiry::class);

        $resolved = $this->app->make(VehicleEnquiry::class);

        $this->assertInstanceOf(CachingVehicleEnquiryService::class, $resolved);
    }
}
