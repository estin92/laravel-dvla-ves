# Contributing

Thanks for considering a contribution to `estin92/laravel-dvla-ves`. This document covers how to get set up, the checks your change needs to pass, and the most common contribution — adding a DVLA value the package doesn't yet recognise.

By taking part you agree to abide by the [Code of Conduct](CODE_OF_CONDUCT.md).

## Getting set up

```bash
git clone https://github.com/estin92/laravel-dvla-ves
cd laravel-dvla-ves
composer install
```

The package targets PHP 8.2–8.4 and Laravel 12–13. Tests run on [Orchestra Testbench](https://github.com/orchestral/testbench), so you don't need a host Laravel app.

## Before you open a PR

Run the same checks CI runs:

```bash
vendor/bin/phpunit       # the test suite
vendor/bin/pint          # code style (run without --test to auto-fix)
```

A PR is ready when the tests pass, Pint reports no style issues, and any behaviour change is covered by a test and noted in [`CHANGELOG.md`](CHANGELOG.md) under the unreleased section.

## Adding an unrecognised DVLA value

When the DVLA returns a value the package hasn't seen:

- For `fuelType`, `taxStatus`, `motStatus`, `euroStatus`, `wheelplan` or `typeApproval`, `fromApi()` coerces the value to the enum's `Unknown` (or `Other`) case and logs a warning.
- For `make`, `KnownMake::isKnown()` returns `false` for the value.

In both cases the value is real DVLA data the package should know about. To add it:

1. Add the case to the relevant enum in `src/Enums/` (or the string to `KnownMake::MAKES` in `src/Support/KnownMake.php`), keeping the DVLA spelling verbatim.
2. For an enum, add a matching key to `lang/en/enums.php` and a case to the enum's `label()`.
3. Add or extend a test in `tests/Unit/` proving the new value resolves correctly.
4. Where possible, say in the PR how you saw the value (e.g. a real registration's response, or the DVLA's published list) so it can be verified.

The enum case lists and `KnownMake` are a point-in-time snapshot of the DVLA's data; see the note in the [README](README.md#validating-make) for provenance.

## Scope

This package exposes **DVLA Vehicle Enquiry Service (VES) response data only**. It is not the DVSA MOT History API or any other source — please don't add MOT test history, mileage, VIN, keeper or model fields to `VehicleData`.

## Reporting bugs and security issues

Open a [GitHub issue](https://github.com/estin92/laravel-dvla-ves/issues) for bugs and feature requests. For anything security-related, follow [`SECURITY.md`](SECURITY.md) instead of filing a public issue.
