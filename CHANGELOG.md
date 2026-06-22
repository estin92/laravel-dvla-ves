# Changelog

All notable changes to `estin92/laravel-dvla-ves` will be documented in this file.

## 2.1.0 - 2026-06-22

### Added
- An `X-Correlation-Id` header is now sent on every lookup (auto-generated UUID when none is supplied). `VehicleEnquiryService::setCorrelationId()` lets a caller pass their own id for the next lookup; the id is recorded in the log context and exposed on every thrown exception via `DvlaVesException::$correlationId`.

## 2.0.0 - 2026-06-19

### Changed
- `VehicleData::$euroStatus`, `$wheelplan` and `$typeApproval` are now backed enums (`?EuroStatus`, `?Wheelplan`, `?TypeApproval`) instead of `?string`. Consumers comparing these to raw strings or passing them to `string`-typed code must update; use `->value` to recover the previous wire string and `->label()` for a translated display string.
- Raised the `phpunit/phpunit` dev requirement to `^11.5|^12.0` (was `^11.0|^12.0`); the test suite now uses assertions added in PHPUnit 11.5.

### Added
- `EuroStatus`, `Wheelplan` and `TypeApproval` enums with `fromApi()` normalisation, an `Unknown` fallback case (logged), and translated `label()`. `EuroStatus` treats the Roman numeral `VI` as `6`, so `Euro VI AG` and `Euro 6 AG` are the same standard, and normalises casing/spacing — `Euro6 AG`, `Euro 6 AG`, `EURO 6 AG`, `Euro VI AG` and `EuroVI AG` all resolve to one canonical case.
- `Support\KnownMake` reference helper (`all()`, `isKnown()`, `canonical()`) for validating the open-ended `make` field, which intentionally remains a `?string`.

## 1.0.0

### Added
- Initial release: DVLA Vehicle Enquiry Service (VES) lookup via `DvlaVes::lookup()`.
- Typed `VehicleData` DTO with `CarbonImmutable` dates and null-safe semantic helpers.
- `FuelType`, `TaxStatus`, `MotStatus` enums with `fromApi()` and translated `label()`.
- Typed exception hierarchy (`DvlaVesException` + four subclasses).
- Opt-in PSR-16 response caching (off by default).
- `DvlaVes::fake()` testing helper.
- `dvla-ves:lookup {registration}` artisan command.
- Laravel Boost guideline + skill.
