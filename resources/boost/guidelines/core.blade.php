{{--
    laravel-dvla-ves — AI guideline for Laravel Boost
    Source: https://github.com/estin92/laravel-dvla-ves
--}}

## DVLA VES (estin92/laravel-dvla-ves)

Looks up UK vehicle data from the **DVLA Vehicle Enquiry Service (VES)** API and returns a typed, immutable `VehicleData` DTO. VES-only: this is **NOT** the DVSA MOT History API and returns no MOT test history, mileage, or advisories.

### Usage

@verbatim
<code-snippet name="Look up a vehicle" lang="php">
use Estin92\DvlaVes\Facades\DvlaVes;

if (DvlaVes::isConfigured()) {
    $vehicle = DvlaVes::lookup('AB12CDE'); // returns Estin92\DvlaVes\Data\VehicleData
    $vehicle->make;           // ?string
    $vehicle->isElectric();   // bool (null-safe over the FuelType enum)
    $vehicle->hasValidMot();  // bool
    $vehicle->taxDueDate;     // ?Carbon\CarbonImmutable
}
</code-snippet>
@endverbatim

### Notes

- Dates (`taxDueDate`, `motExpiryDate`, `artEndDate`, `dateOfLastV5CIssued`) are `CarbonImmutable`.
- `monthOfFirstRegistration` / `monthOfFirstDvlaRegistration` are `"YYYY-MM"` partial dates, typed `CarbonImmutable|string|null` — strings by default, `CarbonImmutable` only when `DVLA_VES_CAST_YEAR_MONTH_ONLY_FIELDS_TO_CARBON=true`. Don't assume the type; call `getFirstRegistrationDate()` for a normalised `?CarbonImmutable`.
- `isSubjectToAdditionalRateOfTax()` is `artEndDate?->isFuture()` — true only while the VED supplement is *currently* in force, not "ever". Raw date via `additionalRateOfTaxEndDate()`.
- Enums: `FuelType`, `TaxStatus`, `MotStatus`, `EuroStatus`, `Wheelplan`, `TypeApproval` — each has `fromApi()`, a translated `label()`, and (the latter three) an `Unknown` fallback case. `euroStatus`/`wheelplan`/`typeApproval` are these enums on `VehicleData`, NOT strings — use `->value` for the wire string.
- A value resolving to `Unknown` (or `make` failing `KnownMake::isKnown()`) is real DVLA data the package hasn't catalogued yet — tell the user to open a PR adding it (https://github.com/estin92/laravel-dvla-ves/pulls).
- `make` is a `?string`, not an enum, because its set is open and ever-growing. To validate it, use `Estin92\DvlaVes\Support\KnownMake`: `KnownMake::isKnown($make)` / `KnownMake::canonical($make)`.
- `DVLA_VES_MODE=sandbox` targets DVLA UAT (default `prod`); `DVLA_VES_DEBUG_LOG_RESPONSES=true` dumps each raw response to `{REGISTRATION}.json`.
- Errors throw a typed hierarchy: `VehicleNotFoundException`, `InvalidRegistrationException`, `RateLimitExceededException`, `ServiceUnavailableException`, all extending `DvlaVesException`.
- `automatedVehicle` (`?bool`) is documented in the DVLA VES v1.2.0 OpenAPI reference (JSON + rendered HTML); only the prose service-description page omits it. Sparsely populated (certain vehicles only). Raw property is nullable; `isAutomatedVehicle()` returns `false` for both `false` and `null`.
- Test without hitting the API: `DvlaVes::fake(['AB12CDE' => ['registrationNumber' => 'AB12CDE', 'make' => 'FORD']])`.
- Do NOT add fields from other sources (MOT History API, Vehicle Data Global) to `VehicleData` — VES response data only.
