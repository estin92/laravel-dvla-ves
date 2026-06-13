<?php

namespace Estin92\DvlaVes\Enums;

use Illuminate\Support\Facades\Log;

enum TaxStatus: string
{
    case Taxed = 'Taxed';
    case Untaxed = 'Untaxed';
    case Sorn = 'SORN';
    case NotTaxable = 'Not Taxed for on Road Use';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $status = self::tryFrom($value);

        if ($status === null) {
            Log::warning('DVLA VES: unrecognised taxStatus value, treated as not taxed', [
                'value' => $value,
            ]);
        }

        return $status;
    }

    public function isTaxed(): bool
    {
        return $this === self::Taxed;
    }

    public function isSorn(): bool
    {
        return $this === self::Sorn;
    }

    public function label(): string
    {
        $key = match ($this) {
            self::Taxed => 'taxed',
            self::Untaxed => 'untaxed',
            self::Sorn => 'sorn',
            self::NotTaxable => 'not_taxable',
        };

        return __("dvla-ves::enums.tax_status.{$key}");
    }
}
