<?php

namespace Estin92\DvlaVes\Tests;

use Estin92\DvlaVes\DvlaVesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            DvlaVesServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('dvla-ves.enabled', true);
        $app['config']->set('dvla-ves.api_key', 'test-api-key');
        $app['config']->set('dvla-ves.mode', 'prod');
        $app['config']->set('dvla-ves.base_urls.prod', 'https://driver-vehicle-licensing.api.gov.uk');
        $app['config']->set('dvla-ves.base_urls.sandbox', 'https://uat.driver-vehicle-licensing.api.gov.uk');
        $app['config']->set('dvla-ves.timeout', 30);
        $app['config']->set('dvla-ves.retry_attempts', 3);
        $app['config']->set('dvla-ves.retry_delay_ms', 100);
    }
}
