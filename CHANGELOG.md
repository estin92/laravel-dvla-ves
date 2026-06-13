# Changelog

All notable changes to `estin92/laravel-dvla-ves` will be documented in this file.

## Unreleased

### Added
- Initial release: DVLA Vehicle Enquiry Service (VES) lookup via `DvlaVes::lookup()`.
- Typed `VehicleData` DTO with `CarbonImmutable` dates and null-safe semantic helpers.
- `FuelType`, `TaxStatus`, `MotStatus` enums with `fromApi()` and translated `label()`.
- Typed exception hierarchy (`DvlaVesException` + four subclasses).
- Opt-in PSR-16 response caching (off by default).
- `DvlaVes::fake()` testing helper.
- `dvla-ves:lookup {registration}` artisan command.
- Laravel Boost guideline + skill.
