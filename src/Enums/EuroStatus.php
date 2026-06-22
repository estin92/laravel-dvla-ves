<?php

namespace Estin92\DvlaVes\Enums;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

enum EuroStatus: string
{
    case Euro6AG = 'Euro6 AG';
    case Euro6BG = 'Euro6 BG';
    case Euro6CG = 'Euro6 CG';
    case Euro6DG = 'Euro6 DG';
    case Euro6AH = 'Euro6 AH';
    case Euro6BH = 'Euro6 BH';
    case Euro6CH = 'Euro6 CH';
    case Euro6AI = 'Euro6 AI';
    case Euro6BI = 'Euro6 BI';
    case Euro6CI = 'Euro6 CI';
    case Euro6AJ = 'Euro6 AJ';
    case Euro6AK = 'Euro6 AK';
    case Euro6AL = 'Euro6 AL';
    case Euro6AM = 'Euro6 AM';
    case Euro6AN = 'Euro6 AN';
    case Euro6AO = 'Euro6 AO';
    case Euro6AP = 'Euro6 AP';
    case Euro6AQ = 'Euro6 AQ';
    case Euro6AR = 'Euro6 AR';

    // Fallback for values not cased above: fromApi() coerces here and logs.
    // When a warning surfaces a new value, raise a PR adding it as a real case.
    case Unknown = 'Unknown';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $suffix = self::normaliseToSuffix($value);

        $status = $suffix === null ? null : self::tryFrom("Euro6 {$suffix}");

        if ($status === null) {
            Log::warning('DVLA VES: unrecognised euroStatus value, coerced to Unknown', [
                'value' => $value,
            ]);

            return self::Unknown;
        }

        return $status;
    }

    private static function normaliseToSuffix(string $value): ?string
    {
        $compact = Str::upper(preg_replace('/\s+/', '', trim($value)) ?? '');

        if (! str_starts_with($compact, 'EURO')) {
            return null;
        }

        $remainder = substr($compact, strlen('EURO'));

        // VI is the Roman numeral 6: the DVLA uses "Euro VI" and "Euro 6"
        // for the same standard, so both reduce to the same suffix.
        if (str_starts_with($remainder, 'VI')) {
            $suffix = substr($remainder, strlen('VI'));
        } elseif (str_starts_with($remainder, '6')) {
            $suffix = substr($remainder, strlen('6'));
        } else {
            return null;
        }

        return preg_match('/^[A-Z]{2}$/', $suffix) === 1 ? $suffix : null;
    }

    public function label(): string
    {
        $key = $this === self::Unknown ? 'unknown' : $this->value;

        return __("dvla-ves::enums.euro_status.{$key}");
    }
}
