<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DVLA VES API Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether the DVLA VES API integration is enabled.
    | When disabled, the service will not make any API calls.
    |
    */
    'enabled' => env('DVLA_VES_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | API Key
    |--------------------------------------------------------------------------
    |
    | Your DVLA VES API key. You can obtain this from the DVLA API portal.
    |
    */
    'api_key' => env('DVLA_VES_API_KEY', env('DVLA_API_KEY')),

    /*
    |--------------------------------------------------------------------------
    | Environment Mode
    |--------------------------------------------------------------------------
    |
    | Which DVLA VES environment to call: 'prod' (live) or 'sandbox' (UAT).
    | The base URL is chosen from the matching entry in 'base_urls' below.
    |
    */
    'mode' => env('DVLA_VES_MODE', 'prod'),

    /*
    |--------------------------------------------------------------------------
    | Base URLs
    |--------------------------------------------------------------------------
    |
    | The base URL for each mode. Sensible DVLA defaults are baked in, but each
    | is independently overridable via env so that if DVLA changes a URL you
    | can point at the new one without waiting for a package release.
    |
    */
    'base_urls' => [
        'prod' => env('DVLA_VES_BASE_URL_PROD', 'https://driver-vehicle-licensing.api.gov.uk'),
        'sandbox' => env('DVLA_VES_BASE_URL_SANDBOX', 'https://uat.driver-vehicle-licensing.api.gov.uk'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout
    |--------------------------------------------------------------------------
    |
    | The request timeout in seconds.
    |
    */
    'timeout' => env('DVLA_VES_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Retry Attempts
    |--------------------------------------------------------------------------
    |
    | The number of retry attempts for failed requests.
    |
    */
    'retry_attempts' => env('DVLA_VES_RETRY_ATTEMPTS', 3),

    /*
    |--------------------------------------------------------------------------
    | Retry Delay
    |--------------------------------------------------------------------------
    |
    | The delay between retry attempts in milliseconds.
    |
    */
    'retry_delay_ms' => env('DVLA_VES_RETRY_DELAY', 100),

    /*
    |--------------------------------------------------------------------------
    | Cast Month Fields To Date
    |--------------------------------------------------------------------------
    |
    | DVLA returns monthOfFirstRegistration and monthOfFirstDvlaRegistration as
    | "YYYY-MM" partial dates. By default they are exposed verbatim as strings.
    | Enable this to receive them as CarbonImmutable instances (start of month)
    | on the VehicleData DTO instead.
    |
    */
    'cast_year_month_only_fields_to_carbon' => env('DVLA_VES_CAST_YEAR_MONTH_ONLY_FIELDS_TO_CARBON', false),

    /*
    |--------------------------------------------------------------------------
    | Debug Logging
    |--------------------------------------------------------------------------
    |
    | When enabled, raw API responses are saved as JSON files for debugging.
    | Files are named after the registration with all non-alphanumerics stripped
    | and uppercased, e.g. "ab12 cde" is written to AB12CDE.json.
    |
    */
    'debug' => [
        'log_responses' => env('DVLA_VES_DEBUG_LOG_RESPONSES', false),
        'disk' => env('DVLA_VES_DEBUG_DISK', 'local'),
        'path' => env('DVLA_VES_DEBUG_PATH', 'dvla-ves-debug'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Response Caching
    |--------------------------------------------------------------------------
    |
    | When enabled, successful lookups are cached keyed by the normalised
    | registration. VES data changes slowly; caching cuts API quota usage.
    | Disabled by default.
    |
    */
    'cache' => [
        'enabled' => env('DVLA_VES_CACHE_ENABLED', false),
        'store' => env('DVLA_VES_CACHE_STORE'),
        'ttl' => env('DVLA_VES_CACHE_TTL', 86400),
        'prefix' => env('DVLA_VES_CACHE_PREFIX', 'dvla-ves'),
    ],
];
