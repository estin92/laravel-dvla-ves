<?php

namespace Estin92\DvlaVes\Services;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Data\VehicleData;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Str;

class CachingVehicleEnquiryService implements VehicleEnquiry
{
    public function __construct(
        private readonly VehicleEnquiry $inner,
        private readonly CacheRepository $cache,
        private readonly int $ttl,
        private readonly string $prefix,
    ) {}

    public function lookup(string $registration): VehicleData
    {
        $key = $this->cacheKey($registration);

        // Cache the raw response array, never the VehicleData object. This keeps
        // the cache payload decoupled from the DTO's shape, and — crucially —
        // sidesteps PHP's unserialize() class allowlist. Laravel exposes that
        // allowlist via cache.serializable_classes; when an app restricts it
        // (e.g. to `false`, which rejects every class), a cached object would
        // come back as __PHP_Incomplete_Class on any serializing store
        // (file/database/redis). An array has no class to allow, so it rehydrates
        // identically regardless of that setting or the Laravel version.
        $response = $this->cache->remember(
            $key,
            $this->ttl,
            fn () => $this->inner->lookup($registration)->rawResponse,
        );

        return VehicleData::fromApiResponse($response);
    }

    public function isConfigured(): bool
    {
        return $this->inner->isConfigured();
    }

    private function cacheKey(string $registration): string
    {
        $normalised = Str::of($registration)->upper()->replace(' ', '')->toString();

        return "{$this->prefix}:vehicle:{$normalised}";
    }
}
