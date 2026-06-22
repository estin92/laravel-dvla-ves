<?php

namespace Estin92\DvlaVes\Tests\Feature;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Exceptions\DvlaVesException;
use Estin92\DvlaVes\Services\VehicleEnquiryService;
use Estin92\DvlaVes\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class BaseUrlModeTest extends TestCase
{
    private function makeService(): VehicleEnquiryService
    {
        $this->app->forgetInstance(VehicleEnquiryService::class);
        $this->app->forgetInstance(VehicleEnquiry::class);
        $this->app->forgetInstance('dvla-ves');

        return $this->app->make(VehicleEnquiryService::class);
    }

    private function assertCalledHost(string $expectedHost): void
    {
        Http::assertSent(fn ($request) => str_starts_with($request->url(), $expectedHost));
    }

    public function test_prod_mode_uses_the_prod_base_url(): void
    {
        config(['dvla-ves.mode' => 'prod']);

        Http::fake(['*' => Http::response(['registrationNumber' => 'AB12CDE'], 200)]);

        $this->makeService()->lookup('AB12CDE');

        $this->assertCalledHost('https://driver-vehicle-licensing.api.gov.uk');
    }

    public function test_sandbox_mode_uses_the_sandbox_base_url(): void
    {
        config(['dvla-ves.mode' => 'sandbox']);

        Http::fake(['*' => Http::response(['registrationNumber' => 'AB12CDE'], 200)]);

        $this->makeService()->lookup('AB12CDE');

        $this->assertCalledHost('https://uat.driver-vehicle-licensing.api.gov.uk');
    }

    public function test_unknown_mode_falls_back_to_prod(): void
    {
        config(['dvla-ves.mode' => 'nonsense']);

        Http::fake(['*' => Http::response(['registrationNumber' => 'AB12CDE'], 200)]);

        $this->makeService()->lookup('AB12CDE');

        $this->assertCalledHost('https://driver-vehicle-licensing.api.gov.uk');
    }

    public function test_per_mode_url_is_overridable(): void
    {
        config([
            'dvla-ves.mode' => 'prod',
            'dvla-ves.base_urls.prod' => 'https://new-dvla-host.example',
        ]);

        Http::fake(['*' => Http::response(['registrationNumber' => 'AB12CDE'], 200)]);

        $this->makeService()->lookup('AB12CDE');

        $this->assertCalledHost('https://new-dvla-host.example');
    }

    public function test_a_plain_http_override_is_upgraded_to_https(): void
    {
        config([
            'dvla-ves.mode' => 'prod',
            'dvla-ves.base_urls.prod' => 'http://new-dvla-host.example',
        ]);

        Http::fake(['*' => Http::response(['registrationNumber' => 'AB12CDE'], 200)]);

        $this->makeService()->lookup('AB12CDE');

        $this->assertCalledHost('https://new-dvla-host.example');
    }

    public function test_a_non_http_base_url_is_rejected(): void
    {
        config([
            'dvla-ves.mode' => 'prod',
            'dvla-ves.base_urls.prod' => 'ftp://new-dvla-host.example',
        ]);

        $this->expectException(DvlaVesException::class);
        $this->expectExceptionMessage('must use HTTPS');

        $this->makeService();
    }
}
