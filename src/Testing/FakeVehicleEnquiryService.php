<?php

namespace Estin92\DvlaVes\Testing;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Exceptions\VehicleNotFoundException;
use Illuminate\Support\Str;
use Throwable;

class FakeVehicleEnquiryService implements VehicleEnquiry
{
    /** @var array<string, array<string, mixed>|VehicleData|Throwable> */
    private array $responses = [];

    /**
     * @param array<string, array<string, mixed>|VehicleData|Throwable> $responses
     */
    public function __construct(array $responses = [])
    {
        foreach ($responses as $registration => $response) {
            $this->responses[$this->normalise($registration)] = $response;
        }
    }

    public function lookup(string $registration): VehicleData
    {
        $key = $this->normalise($registration);

        if (! array_key_exists($key, $this->responses)) {
            throw new VehicleNotFoundException($key);
        }

        $response = $this->responses[$key];

        if ($response instanceof Throwable) {
            throw $response;
        }

        if ($response instanceof VehicleData) {
            return $response;
        }

        return VehicleData::fromApiResponse($response);
    }

    public function isConfigured(): bool
    {
        return true;
    }

    private function normalise(string $registration): string
    {
        return Str::of($registration)->upper()->replace(' ', '')->toString();
    }
}
