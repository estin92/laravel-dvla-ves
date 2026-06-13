<?php

namespace Estin92\DvlaVes\Console;

use BackedEnum;
use Carbon\CarbonInterface;
use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Exceptions\DvlaVesException;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\spin;

class LookupVehicleCommand extends Command
{
    protected $signature = 'dvla-ves:lookup {registration : The vehicle registration number}';

    protected $description = 'Look up a vehicle by registration via the DVLA VES API';

    /**
     * Restores DVLA acronyms that Str::headline() lower-cases.
     *
     * @var array<string, string>
     */
    private const ACRONYM_FIXES = [
        'Co2' => 'CO2',
        'Mot' => 'MOT',
        'Dvla' => 'DVLA',
        'V5 C' => 'V5C',
    ];

    public function handle(VehicleEnquiry $service): int
    {
        $registration = (string) $this->argument('registration');

        if (! $service->isConfigured()) {
            $this->error('DVLA VES is not configured (missing API key or disabled).');

            return self::FAILURE;
        }

        try {
            $data = spin(
                fn () => $service->lookup($registration),
                "Looking up {$registration} with the DVLA VES API...",
            );
        } catch (DvlaVesException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Field', 'Value'], $this->rows($data));

        return self::SUCCESS;
    }

    /**
     * Build a row per VehicleData attribute, excluding the raw response array.
     *
     * @return array<int, array{0: string, 1: string}>
     */
    private function rows(VehicleData $data): array
    {
        $rows = [];

        foreach (get_object_vars($data) as $property => $value) {
            if ($property === 'rawResponse') {
                continue;
            }

            $rows[] = [$this->label($property), $this->display($value)];
        }

        return $rows;
    }

    private function label(string $property): string
    {
        return str_replace(
            array_keys(self::ACRONYM_FIXES),
            array_values(self::ACRONYM_FIXES),
            Str::headline($property),
        );
    }

    private function display(mixed $value): string
    {
        if ($value === null) {
            return '—';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toDateString();
        }

        return (string) $value;
    }
}
