<?php

namespace Estin92\DvlaVes\Enums;

use Illuminate\Support\Facades\Log;

enum Wheelplan: string
{
    case TwoWheel = '2 WHEEL';
    case ThreeWheel = '3 WHEEL';
    case TwoAxleRigidBody = '2 AXLE RIGID BODY';
    case ThreeAxleRigidBody = '3 AXLE RIGID BODY';
    case MultiAxleRigid = 'MULTI AXLE RIGID';
    case ThreeWheelArtic = '3 WHEEL + ARTIC';
    case TwoAxleArtic = '2 AXLE + ARTIC';
    case ThreeAxleArtic = '3 AXLE + ARTIC';
    case MultiAxleArtic = 'MULTI AXLE + ARTIC';
    case Crawler = 'CRAWLER';
    case TwoAxleTwoAxleArtic = '2 AXLE + 2 AXLE ARTIC';
    case TwoAxleThreeAxleArtic = '2 AXLE + 3 AXLE ARTIC';
    case ThreeAxleTwoAxleArtic = '3 AXLE + 2 AXLE ARTIC';
    case ThreeAxleThreeAxleArtic = '3 AXLE + 3 AXLE ARTIC';
    case NonStandard = 'NON STANDARD';
    case NotRecorded = 'NOT RECORDED';
    case Articulated = 'ARTICULATED';

    // Fallback for values not cased above: fromApi() coerces here and logs.
    // When a warning surfaces a new value, raise a PR adding it as a real case.
    case Unknown = 'Unknown';

    public static function fromApi(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        $normalised = self::normalise($value);

        if ($normalised === '') {
            return null;
        }

        $wheelplan = self::tryFrom($normalised);

        if ($wheelplan === null) {
            Log::warning('DVLA VES: unrecognised wheelplan value, coerced to Unknown', [
                'value' => $value,
            ]);

            return self::Unknown;
        }

        return $wheelplan;
    }

    private static function normalise(string $value): string
    {
        $collapsed = preg_replace('/\s+/', ' ', trim($value)) ?? '';

        return strtoupper($collapsed);
    }

    public function label(): string
    {
        $key = match ($this) {
            self::TwoWheel => 'two_wheel',
            self::ThreeWheel => 'three_wheel',
            self::TwoAxleRigidBody => 'two_axle_rigid_body',
            self::ThreeAxleRigidBody => 'three_axle_rigid_body',
            self::MultiAxleRigid => 'multi_axle_rigid',
            self::ThreeWheelArtic => 'three_wheel_artic',
            self::TwoAxleArtic => 'two_axle_artic',
            self::ThreeAxleArtic => 'three_axle_artic',
            self::MultiAxleArtic => 'multi_axle_artic',
            self::Crawler => 'crawler',
            self::TwoAxleTwoAxleArtic => 'two_axle_two_axle_artic',
            self::TwoAxleThreeAxleArtic => 'two_axle_three_axle_artic',
            self::ThreeAxleTwoAxleArtic => 'three_axle_two_axle_artic',
            self::ThreeAxleThreeAxleArtic => 'three_axle_three_axle_artic',
            self::NonStandard => 'non_standard',
            self::NotRecorded => 'not_recorded',
            self::Articulated => 'articulated',
            self::Unknown => 'unknown',
        };

        return __("dvla-ves::enums.wheelplan.{$key}");
    }
}
