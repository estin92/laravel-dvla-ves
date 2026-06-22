<?php

namespace Estin92\DvlaVes\Enums;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

enum TypeApproval: string
{
    case L1 = 'L1';
    case L2 = 'L2';
    case L3 = 'L3';
    case L4 = 'L4';
    case L5 = 'L5';
    case L6 = 'L6';
    case L7 = 'L7';
    case M1 = 'M1';
    case M2 = 'M2';
    case M3 = 'M3';
    case M1SP = 'M1SP';
    case N1 = 'N1';
    case N2 = 'N2';
    case N3 = 'N3';
    case T1 = 'T1';
    case T2 = 'T2';
    case T3 = 'T3';

    // Fallback for values not cased above: fromApi() coerces here and logs.
    // When a warning surfaces a new value, raise a PR adding it as a real case.
    case Unknown = 'Unknown';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalised = Str::upper(trim($value));

        if ($normalised === '') {
            return null;
        }

        $approval = self::tryFrom($normalised);

        if ($approval === null) {
            Log::warning('DVLA VES: unrecognised typeApproval value, coerced to Unknown', [
                'value' => $value,
            ]);

            return self::Unknown;
        }

        return $approval;
    }

    public function label(): string
    {
        return __("dvla-ves::enums.type_approval.{$this->value}");
    }
}
