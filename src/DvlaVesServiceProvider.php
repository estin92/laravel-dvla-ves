<?php

namespace Estin92\DvlaVes;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Services\CachingVehicleEnquiryService;
use Estin92\DvlaVes\Services\VehicleEnquiryService;
use Illuminate\Support\ServiceProvider;

class DvlaVesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dvla-ves.php', 'dvla-ves');

        $this->app->singleton(VehicleEnquiryService::class, fn () => new VehicleEnquiryService(
            apiKey: config('dvla-ves.api_key'),
            baseUrl: $this->resolveBaseUrl(),
            timeout: config('dvla-ves.timeout'),
            retryAttempts: config('dvla-ves.retry_attempts'),
            retryDelayMs: config('dvla-ves.retry_delay_ms'),
        ));

        $this->app->singleton(VehicleEnquiry::class, function ($app) {
            $service = $app->make(VehicleEnquiryService::class);

            if (! config('dvla-ves.cache.enabled')) {
                return $service;
            }

            return new CachingVehicleEnquiryService(
                inner: $service,
                cache: $app->make('cache')->store(config('dvla-ves.cache.store')),
                ttl: (int) config('dvla-ves.cache.ttl'),
                prefix: (string) config('dvla-ves.cache.prefix'),
            );
        });

        $this->app->alias(VehicleEnquiry::class, 'dvla-ves');
    }

    private function resolveBaseUrl(): string
    {
        $mode = config('dvla-ves.mode') === 'sandbox' ? 'sandbox' : 'prod';

        return (string) config("dvla-ves.base_urls.{$mode}");
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'dvla-ves');

        if ($this->app->runningInConsole()) {
            $this->commands([
                \Estin92\DvlaVes\Console\LookupVehicleCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../config/dvla-ves.php' => config_path('dvla-ves.php'),
            ], 'dvla-ves-config');

            $this->registerTranslationPublishing();
        }
    }

    private function registerTranslationPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../lang' => $this->app->langPath('vendor/dvla-ves'),
        ], 'dvla-ves-lang');
    }
}
