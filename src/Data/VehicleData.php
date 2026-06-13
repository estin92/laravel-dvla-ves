<?php

namespace Estin92\DvlaVes\Data;

use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Estin92\DvlaVes\Enums\FuelType;
use Estin92\DvlaVes\Enums\MotStatus;
use Estin92\DvlaVes\Enums\TaxStatus;
use Estin92\DvlaVes\Exceptions\DvlaVesException;
use Illuminate\Support\Facades\Log;

class VehicleData
{
    public function __construct(
        public readonly string $registrationNumber,
        public readonly ?string $make,
        public readonly ?string $colour,
        public readonly ?FuelType $fuelType,
        public readonly ?int $engineCapacity,
        public readonly ?int $co2Emissions,
        public readonly ?int $revenueWeight,
        public readonly ?TaxStatus $taxStatus,
        public readonly ?CarbonImmutable $taxDueDate,
        public readonly ?MotStatus $motStatus,
        public readonly ?CarbonImmutable $motExpiryDate,
        public readonly ?int $yearOfManufacture,
        /**
         * "YYYY-MM" string by default; a CarbonImmutable (start of month) when
         * the dvla-ves.cast_year_month_only_fields_to_carbon config flag is enabled.
         */
        public readonly CarbonImmutable|string|null $monthOfFirstRegistration,
        public readonly CarbonImmutable|string|null $monthOfFirstDvlaRegistration,
        public readonly ?string $euroStatus,
        public readonly ?string $realDrivingEmissions,
        public readonly ?string $wheelplan,
        /** Additional Rate of Tax (luxury-car VED supplement) end date. */
        public readonly ?CarbonImmutable $artEndDate,
        public readonly ?string $typeApproval,
        public readonly ?bool $markedForExport,
        /**
         * Automated Vehicle (AV) flag, documented in the DVLA VES v1.2.0 OpenAPI
         * reference (both the JSON spec and its rendered HTML); only the separate
         * prose service-description page omits it. Optional and sparsely
         * populated: in real captures DVLA returned it for certain vehicles only
         * (not even all EVs), so it is null whenever DVLA omits it.
         */
        public readonly ?bool $automatedVehicle,
        public readonly ?CarbonImmutable $dateOfLastV5CIssued,
        /** @var array<string, mixed> The verbatim DVLA VES response body. */
        public readonly array $rawResponse,
    ) {}

    public static function fromApiResponse(array $response): self
    {
        if (! isset($response['registrationNumber'])) {
            throw new DvlaVesException('Malformed DVLA VES response: missing registrationNumber');
        }

        return new self(
            registrationNumber: $response['registrationNumber'],
            make: $response['make'] ?? null,
            colour: $response['colour'] ?? null,
            fuelType: FuelType::fromApi($response['fuelType'] ?? null),
            engineCapacity: self::intOrNull($response, 'engineCapacity'),
            co2Emissions: self::intOrNull($response, 'co2Emissions'),
            revenueWeight: self::intOrNull($response, 'revenueWeight'),
            taxStatus: TaxStatus::fromApi($response['taxStatus'] ?? null),
            taxDueDate: self::dateOrNull($response, 'taxDueDate'),
            motStatus: MotStatus::fromApi($response['motStatus'] ?? null),
            motExpiryDate: self::dateOrNull($response, 'motExpiryDate'),
            yearOfManufacture: self::intOrNull($response, 'yearOfManufacture'),
            monthOfFirstRegistration: self::monthOrNull($response, 'monthOfFirstRegistration'),
            monthOfFirstDvlaRegistration: self::monthOrNull($response, 'monthOfFirstDvlaRegistration'),
            euroStatus: $response['euroStatus'] ?? null,
            realDrivingEmissions: $response['realDrivingEmissions'] ?? null,
            wheelplan: $response['wheelplan'] ?? null,
            artEndDate: self::dateOrNull($response, 'artEndDate'),
            typeApproval: $response['typeApproval'] ?? null,
            markedForExport: $response['markedForExport'] ?? null,
            automatedVehicle: $response['automatedVehicle'] ?? null,
            dateOfLastV5CIssued: self::dateOrNull($response, 'dateOfLastV5CIssued'),
            rawResponse: $response,
        );
    }

    private static function intOrNull(array $data, string $key): ?int
    {
        return isset($data[$key]) ? (int) $data[$key] : null;
    }

    private static function dateOrNull(array $data, string $key): ?CarbonImmutable
    {
        if (! isset($data[$key])) {
            return null;
        }

        try {
            return CarbonImmutable::parse($data[$key]);
        } catch (InvalidFormatException $e) {
            throw new DvlaVesException(
                "Malformed DVLA VES response: unparseable date in '{$key}'",
                previous: $e,
            );
        }
    }

    /**
     * A "YYYY-MM" value: returned verbatim as a string, or parsed to a
     * start-of-month CarbonImmutable when dvla-ves.cast_year_month_only_fields_to_carbon
     * is enabled.
     */
    private static function monthOrNull(array $data, string $key): CarbonImmutable|string|null
    {
        if (! isset($data[$key])) {
            return null;
        }

        $value = $data[$key];

        if (! self::shouldCastMonthFields()) {
            return $value;
        }

        // A malformed "YYYY-MM" must not crash the response hydration. Fall back
        // to the raw string, matching the casting-disabled path and preserving
        // whatever DVLA sent. createFromFormat throws InvalidFormatException in
        // Carbon's default strict mode, but returns null when an app has called
        // Carbon::useStrictMode(false) — both paths must reach the same fallback.
        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m', $value);
        } catch (InvalidFormatException $e) {
            $parsed = null;
        }

        if (! $parsed instanceof CarbonImmutable) {
            Log::debug('DVLA VES: unparseable month field, preserving raw value', [
                'key' => $key,
                'value' => $value,
            ]);

            return $value;
        }

        return $parsed->startOfMonth();
    }

    private static function shouldCastMonthFields(): bool
    {
        return function_exists('config') && (bool) config('dvla-ves.cast_year_month_only_fields_to_carbon');
    }

    public function getFirstRegistrationDate(): ?CarbonImmutable
    {
        if (! $this->monthOfFirstRegistration) {
            return null;
        }

        if ($this->monthOfFirstRegistration instanceof CarbonImmutable) {
            return $this->monthOfFirstRegistration;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('Y-m', $this->monthOfFirstRegistration);
        } catch (InvalidFormatException $e) {
            $parsed = null;
        }

        if (! $parsed instanceof CarbonImmutable) {
            Log::debug('DVLA VES: unparseable monthOfFirstRegistration', [
                'value' => $this->monthOfFirstRegistration,
            ]);

            return null;
        }

        return $parsed->startOfMonth();
    }

    public function isPetrol(): bool
    {
        return $this->fuelType?->isPetrol() ?? false;
    }

    public function isDiesel(): bool
    {
        return $this->fuelType?->isDiesel() ?? false;
    }

    public function isElectric(): bool
    {
        return $this->fuelType?->isElectric() ?? false;
    }

    public function isHybrid(): bool
    {
        return $this->fuelType?->isHybrid() ?? false;
    }

    public function isTaxed(): bool
    {
        return $this->taxStatus?->isTaxed() ?? false;
    }

    public function isSorn(): bool
    {
        return $this->taxStatus?->isSorn() ?? false;
    }

    public function isTaxDue(): bool
    {
        return $this->taxStatus === TaxStatus::Untaxed;
    }

    public function hasValidMot(): bool
    {
        return $this->motStatus?->isValid() ?? false;
    }

    public function isMarkedForExport(): bool
    {
        return $this->markedForExport ?? false;
    }

    public function isAutomatedVehicle(): bool
    {
        return $this->automatedVehicle ?? false;
    }

    /**
     * Additional Rate of Tax (luxury-car VED supplement) end date. Alias for
     * the cryptic `artEndDate` wire field.
     */
    public function additionalRateOfTaxEndDate(): ?CarbonImmutable
    {
        return $this->artEndDate;
    }

    public function isSubjectToAdditionalRateOfTax(): bool
    {
        return $this->artEndDate?->isFuture() ?? false;
    }
}
