---
name: dvla-ves-development
description: Build and work with the estin92/laravel-dvla-ves package — looking up UK vehicle data from the DVLA Vehicle Enquiry Service (VES), handling its typed VehicleData DTO, enums and exceptions, opt-in caching, the artisan command, and testing with the fake driver.
license: MIT
metadata:
  author: Ian Austin
---

# DVLA VES Development

## When to use this skill

Use when integrating, configuring, or testing the `estin92/laravel-dvla-ves` package — any code that calls `DvlaVes::lookup()`, reads `VehicleData`, handles its enums/exceptions, enables caching, or tests vehicle lookups.

## Scope boundary

This package returns **DVLA VES response data only**. It is NOT the DVSA MOT History API (`history.mot.api.gov.uk`) and NOT Vehicle Data Global. Never add MOT test history, mileage, advisories, VIN, keeper, or model fields to `VehicleData`.

## Core usage

    use Estin92\DvlaVes\Facades\DvlaVes;

    $vehicle = DvlaVes::lookup('AB12CDE');   // Estin92\DvlaVes\Data\VehicleData
    $vehicle->isElectric();                  // null-safe helpers
    $vehicle->isSubjectToAdditionalRateOfTax();

`lookup()` throws `VehicleNotFoundException` (404), `InvalidRegistrationException` (400), `RateLimitExceededException` (429), `ServiceUnavailableException` (5xx), all extending `DvlaVesException`. Always wrap in try/catch or guard with `DvlaVes::isConfigured()`.

`isSubjectToAdditionalRateOfTax()` is `artEndDate?->isFuture()` — it reports whether the luxury-car VED supplement is **currently** in force, returning `false` once the supplement period has ended (or when DVLA reports no `artEndDate`). It is not "was this car ever subject to ART". Use `additionalRateOfTaxEndDate()` for the raw end date.

## Configuration

Publish config with `php artisan vendor:publish --tag=dvla-ves-config`. Minimum required: `DVLA_VES_API_KEY`. Optional opt-in caching: set `DVLA_VES_CACHE_ENABLED=true` (TTL via `DVLA_VES_CACHE_TTL`, default 86400s).

Other env switches:

- `DVLA_VES_MODE=sandbox` targets DVLA's UAT environment (default `prod`); each mode's base URL is independently overridable via `DVLA_VES_BASE_URL_PROD` / `DVLA_VES_BASE_URL_SANDBOX`.
- `DVLA_VES_DEBUG_LOG_RESPONSES=true` writes each raw API response to disk as `{REGISTRATION}.json` (disk/path via `DVLA_VES_DEBUG_DISK` / `DVLA_VES_DEBUG_PATH`).

## Partial-date (YYYY-MM) fields

`monthOfFirstRegistration` and `monthOfFirstDvlaRegistration` are returned by DVLA as `"YYYY-MM"` partial dates. By default `VehicleData` exposes them **verbatim as strings**. Set `DVLA_VES_CAST_YEAR_MONTH_ONLY_FIELDS_TO_CARBON=true` to receive them as start-of-month `CarbonImmutable` instead. Either way the property type is the union `CarbonImmutable|string|null`, so don't assume one — call `getFirstRegistrationDate()` for a normalised `?CarbonImmutable` regardless of the flag.

## Artisan

    php artisan dvla-ves:lookup AB12CDE

## Testing

Use the fake to avoid live calls:

    DvlaVes::fake([
        'AB12CDE' => ['registrationNumber' => 'AB12CDE', 'make' => 'FORD', 'fuelType' => 'ELECTRICITY'],
        'NONE123' => new \Estin92\DvlaVes\Exceptions\VehicleNotFoundException('NONE123'),
    ]);

Unconfigured registrations throw `VehicleNotFoundException`.

## Automated Vehicle field

`VehicleData::$automatedVehicle` (`?bool`) is documented in the DVLA VES v1.2.0 OpenAPI reference (JSON + rendered HTML); only the prose service-description page omits it. Sparsely populated — DVLA returns it for certain vehicles only. The raw property is nullable so you can tell "DVLA reported false" from "DVLA omitted it". For a plain yes/no use `isAutomatedVehicle()`, which returns `false` for both `false` and `null` — absence means "not an automated vehicle" (DVLA would have flagged a genuine AV).
