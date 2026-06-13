<?php

namespace Estin92\DvlaVes\Enums;

use Illuminate\Support\Facades\Log;

enum MotStatus: string
{
    case Valid = 'Valid';
    case NotValid = 'Not valid';
    case NoDetailsHeld = 'No details held by DVLA';
    case NoResultsReturned = 'No results returned';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $status = self::tryFrom($value);

        if ($status === null) {
            Log::warning('DVLA VES: unrecognised motStatus value, treated as no valid MOT', [
                'value' => $value,
            ]);
        }

        return $status;
    }

    public function isValid(): bool
    {
        return $this === self::Valid;
    }

    public function label(): string
    {
        $key = match ($this) {
            self::Valid => 'valid',
            self::NotValid => 'not_valid',
            self::NoDetailsHeld => 'no_details_held',
            self::NoResultsReturned => 'no_results_returned',
        };

        return __("dvla-ves::enums.mot_status.{$key}");
    }
}
