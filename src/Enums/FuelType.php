<?php

namespace Estin92\DvlaVes\Enums;

use Illuminate\Support\Str;

/**
 * Cases mirror the DVLA Vehicle Enquiry Service API's `fuelType` response
 * strings, verified against live responses. Any value DVLA returns that
 * isn't one of these (rare in practice) coerces to Other via fromApi() so
 * downstream consumers always receive a typed value.
 */
enum FuelType: string
{
    case Petrol = 'PETROL';
    case Diesel = 'DIESEL';
    case Electricity = 'ELECTRICITY';
    case HybridElectric = 'HYBRID ELECTRIC';
    case ElectricDiesel = 'ELECTRIC DIESEL';
    case GasBiFuel = 'GAS BI-FUEL';
    case Other = 'OTHER';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(Str::upper($value)) ?? self::Other;
    }

    public function is(self $type): bool
    {
        return $this === $type;
    }

    public function isPetrol(): bool
    {
        return $this === self::Petrol;
    }

    public function isDiesel(): bool
    {
        return $this === self::Diesel;
    }

    public function isElectric(): bool
    {
        return $this === self::Electricity;
    }

    public function isHybrid(): bool
    {
        return $this === self::HybridElectric;
    }

    public function label(): string
    {
        $key = match ($this) {
            self::Petrol => 'petrol',
            self::Diesel => 'diesel',
            self::Electricity => 'electricity',
            self::HybridElectric => 'hybrid_electric',
            self::ElectricDiesel => 'electric_diesel',
            self::GasBiFuel => 'gas_bi_fuel',
            self::Other => 'other',
        };

        return __("dvla-ves::enums.fuel_type.{$key}");
    }
}
