# Laravel DVLA VES

**Laravel DVLA VES** is a PHP package that looks up UK vehicle details by registration number through the official DVLA Vehicle Enquiry Service (VES) API. 

Pass a number plate and it returns a typed, immutable `VehicleData` object exposing tax status, MOT status and expiry, fuel type, CO2 emissions, make, colour, year of manufacture and more. 

It is built for Laravel 11, 12 and 13 on PHP 8.2+, with first-class enums, response caching, a fake driver for testing and an Artisan lookup command. This package is an independent open-source connector and is not affiliated with or endorsed by the DVLA.

## Install

```bash
composer require estin92/laravel-dvla-ves
```

## Configure

```bash
php artisan vendor:publish --tag=dvla-ves-config
```

Set your API key in `.env`:

```dotenv
DVLA_VES_API_KEY=your-key
```

> You supply your own DVLA VES API key, obtained from the [DVLA Developer Portal](https://developer-portal.driver-vehicle-licensing.api.gov.uk/). Your use of the API is governed by DVLA's own terms of use - this package is an independent connector and is not affiliated with or endorsed by the DVLA.

## Usage

```php
use Estin92\DvlaVes\Facades\DvlaVes;

$vehicle = DvlaVes::lookup('AB12CDE');
```

`lookup()` returns a readonly `VehicleData`. Every wire field is exposed as a typed property - all nullable except `registrationNumber` (see [Why is almost every field nullable?](#why-is-almost-every-field-nullable) below):

```php
// Identity & build
$vehicle->registrationNumber;            // string            "AB12CDE"
$vehicle->make;                          // ?string           "FORD"
$vehicle->colour;                        // ?string           "BLUE"
$vehicle->yearOfManufacture;             // ?int              2012
$vehicle->wheelplan;                     // ?string           "2 AXLE RIGID BODY"
$vehicle->typeApproval;                  // ?string           "M1"

// Engine & emissions
$vehicle->fuelType;                      // ?FuelType (enum)
$vehicle->engineCapacity;                // ?int              1560   (cc)
$vehicle->co2Emissions;                  // ?int              104    (g/km)
$vehicle->revenueWeight;                 // ?int              null   (kg, goods vehicles)
$vehicle->euroStatus;                    // ?string           "EURO 6"
$vehicle->realDrivingEmissions;          // ?string           "1"

// Tax
$vehicle->taxStatus;                     // ?TaxStatus (enum)
$vehicle->taxDueDate;                    // ?CarbonImmutable
$vehicle->artEndDate;                    // ?CarbonImmutable   (luxury-car VED supplement end)

// MOT
$vehicle->motStatus;                     // ?MotStatus (enum)
$vehicle->motExpiryDate;                 // ?CarbonImmutable

// Registration history
$vehicle->monthOfFirstRegistration;      // ?string "2012-03"  (or ?CarbonImmutable, see below)
$vehicle->monthOfFirstDvlaRegistration;  // ?string "2012-03"  (or ?CarbonImmutable, see below)
$vehicle->dateOfLastV5CIssued;           // ?CarbonImmutable

// Flags
$vehicle->markedForExport;               // ?bool
$vehicle->automatedVehicle;              // ?bool              (see note below)

// The untouched decoded API payload, if you need a field not surfaced above
$vehicle->rawResponse;                   // array<string, mixed>
```

Domain helpers wrap the nullable enums/flags and always return a plain `bool`:

```php
$vehicle->isPetrol();                    // fuelType === Petrol
$vehicle->isDiesel();                    // fuelType === Diesel
$vehicle->isElectric();                  // fuelType === Electricity
$vehicle->isHybrid();                    // fuelType === HybridElectric

$vehicle->isTaxed();                     // taxStatus === Taxed
$vehicle->isSorn();                      // taxStatus === Sorn
$vehicle->isTaxDue();                    // taxStatus === Untaxed
$vehicle->hasValidMot();                 // motStatus === Valid

$vehicle->isMarkedForExport();           // markedForExport ?? false
$vehicle->isAutomatedVehicle();          // automatedVehicle ?? false
$vehicle->isSubjectToAdditionalRateOfTax(); // artEndDate is in the future
```

Accessors return a typed value rather than a `bool`:

```php
$vehicle->getFirstRegistrationDate();    // ?CarbonImmutable (start of month), parsed on demand
$vehicle->additionalRateOfTaxEndDate();  // ?CarbonImmutable  alias for artEndDate
```

The enums carry their own helpers and a translatable label:

```php
$vehicle->fuelType?->label();            // "Petrol"        (translatable via dvla-ves::enums)
$vehicle->taxStatus?->label();           // "Taxed"
$vehicle->motStatus?->isValid();         // bool

if ($vehicle->fuelType?->isElectric()) {
    // ...
}
```

Failures throw - catch the base exception or a specific subclass:

```php
use Estin92\DvlaVes\Exceptions\DvlaVesException;
use Estin92\DvlaVes\Exceptions\VehicleNotFoundException;
use Estin92\DvlaVes\Exceptions\RateLimitExceededException;

try {
    $vehicle = DvlaVes::lookup('AB12CDE');
} catch (VehicleNotFoundException $e) {
    // 404 - no vehicle for that registration
} catch (RateLimitExceededException $e) {
    $e->retryAfter;                      // ?int seconds, from the Retry-After header
} catch (DvlaVesException $e) {
    // any other DVLA VES failure (invalid registration, service unavailable, ...)
    report($e);
}
```

> The `monthOfFirstRegistration` / `monthOfFirstDvlaRegistration` fields are `"YYYY-MM"`
> strings by default. Either call `getFirstRegistrationDate()` for a `CarbonImmutable` or
> set `DVLA_VES_CAST_YEAR_MONTH_ONLY_FIELDS_TO_CARBON=true` to receive these properties as
> `CarbonImmutable` (start of month) directly.

## Why is almost every field nullable?

DVLA has been tested and found to return a different subset of fields per vehicle, omitting the ones
that don't apply rather than returning them as `null`. Every property except `registrationNumber` is
typed `?` and defaults to `null` when its key is absent. Across **21 real vehicle lookups**, the
number of responses that contained each field was:

| Field | Present |
|---|---|
| `registrationNumber` | 21/21 |
| `make` | 21/21 |
| `colour` | 21/21 |
| `fuelType` | 21/21 |
| `taxStatus` | 21/21 |
| `motStatus` | 21/21 |
| `yearOfManufacture` | 21/21 |
| `monthOfFirstRegistration` | 21/21 |
| `wheelplan` | 21/21 |
| `markedForExport` | 21/21 |
| `dateOfLastV5CIssued` | 21/21 |
| `engineCapacity` | 20/21 |
| `co2Emissions` | 19/21 |
| `taxDueDate` | 18/21 |
| `typeApproval` | 17/21 |
| `motExpiryDate` | 15/21 |
| `revenueWeight` | 10/21 |
| `euroStatus` | 4/21 |
| `artEndDate` | 2/21 |
| `automatedVehicle` | 2/21 |
| `realDrivingEmissions` | 1/21 |
| `monthOfFirstDvlaRegistration` | 1/21 |

We have opted to keep all fields, including those that appeared in all 21, nullable too - the sample
can't guarantee every vehicle returns them. No captured response contained an explicit `null`; DVLA
seems instead to only ever omit attributes. Typing every field as nullable means an omitted attribute
defaults to `null` rather than triggering an unnecessary fatal error during hydration. To avoid null
checks, use the boolean helpers (e.g. `isElectric()`, `hasValidMot()`), which treat a missing value
as `false`.

## API reference

- `DvlaVes::lookup(string $registration): VehicleData`
- `DvlaVes::isConfigured(): bool`.
- `VehicleData` - readonly DTO; full property and helper list shown under [Usage](#usage).
- Enums: `FuelType`, `TaxStatus`, `MotStatus` (each with `fromApi()` + translated `label()`).
- Exceptions: `VehicleNotFoundException`, `InvalidRegistrationException`, `RateLimitExceededException`, `ServiceUnavailableException` - all extend `DvlaVesException`.

> `VehicleData::$automatedVehicle` (`?bool`) is documented in the DVLA VES v1.2.0 OpenAPI reference (both the JSON spec and its rendered HTML), though DVLA's separate prose service-description page omits it. It is sparsely included and in practice, our testing has shown the DVLA only returns it for certain vehicles - so the raw property is nullable. Use `isAutomatedVehicle()` for a plain boolean; it returns `false` for both `false` and `null`, since a vehicle DVLA never flags is not an automated vehicle.

## Caching (off by default)

```dotenv
DVLA_VES_CACHE_ENABLED=true
DVLA_VES_CACHE_TTL=86400
```

Caches successful lookups keyed by normalised registration.

## Testing

```php
DvlaVes::fake([
    'AB12CDE' => ['registrationNumber' => 'AB12CDE', 'make' => 'FORD'],
]);

DvlaVes::lookup('AB12CDE')->make; // "FORD"
```

## Artisan

```bash
php artisan dvla-ves:lookup AB12CDE
```

## AI agent support (Laravel Boost)

This package ships [Laravel Boost](https://laravel.com/docs/13.x/boost) resources - an AI guideline and a `dvla-ves` skill - so coding agents (Claude Code, Cursor, etc.) get accurate context about `VehicleData`, the enums, exceptions, caching and the fake driver.

Boost surfaces them once it is made aware of this package:

- If you install or reconfigure Boost with `php artisan boost:install`, this package's guideline and skill are offered for publishing.
- In a project where Boost is already installed, scan for this newly added package and be prompted to publish its resources with:

```bash
php artisan boost:update --discover
```

Plain `php artisan boost:update` only refreshes resources you have already published - it will not discover this package on its own, so use `--discover` (or re-run `boost:install`) the first time. Once published, the resources are recorded in `boost.json` and stay current on every `boost:update`. To automate that, add Boost's update command to your app's `composer.json` `post-update-cmd` scripts.

## License

MIT. See [LICENSE](LICENSE).
