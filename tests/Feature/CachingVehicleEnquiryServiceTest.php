<?php

namespace Estin92\DvlaVes\Tests\Feature;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Services\CachingVehicleEnquiryService;
use Estin92\DvlaVes\Tests\TestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class CachingVehicleEnquiryServiceTest extends TestCase
{
    public function test_second_lookup_for_same_reg_hits_cache_not_inner(): void
    {
        $inner = new class implements VehicleEnquiry
        {
            public int $calls = 0;

            public function lookup(string $registration): VehicleData
            {
                $this->calls++;

                return VehicleData::fromApiResponse(['registrationNumber' => $registration, 'make' => 'FORD']);
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };

        $caching = new CachingVehicleEnquiryService($inner, Cache::store(), 3600, 'dvla-ves');

        $first = $caching->lookup('AB12CDE');
        $second = $caching->lookup('AB12CDE');

        $this->assertSame('FORD', $first->make);
        $this->assertSame('FORD', $second->make);
        $this->assertSame(1, $inner->calls, 'Inner service must only be hit once; second call is cached');
    }

    public function test_different_regs_are_cached_separately(): void
    {
        $inner = new class implements VehicleEnquiry
        {
            public int $calls = 0;

            public function lookup(string $registration): VehicleData
            {
                $this->calls++;

                return VehicleData::fromApiResponse(['registrationNumber' => $registration]);
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };

        $caching = new CachingVehicleEnquiryService($inner, Cache::store(), 3600, 'dvla-ves');

        $caching->lookup('AB12CDE');
        $caching->lookup('XY99ZZZ');

        $this->assertSame(2, $inner->calls);
    }

    public function test_is_configured_delegates_to_inner(): void
    {
        $inner = new class implements VehicleEnquiry
        {
            public function lookup(string $registration): VehicleData
            {
                return VehicleData::fromApiResponse(['registrationNumber' => $registration]);
            }

            public function isConfigured(): bool
            {
                return false;
            }
        };

        $caching = new CachingVehicleEnquiryService($inner, Cache::store(), 3600, 'dvla-ves');

        $this->assertFalse($caching->isConfigured());
    }

    public function test_it_caches_a_plain_array_not_a_vehicle_data_object(): void
    {
        // Laravel 13's cache.serializable_classes defaults to false, meaning
        // unserialize() blocks ALL classes and returns __PHP_Incomplete_Class.
        // Caching the raw array (not the object) sidesteps the allowlist
        // entirely, so the decorator works on every store and every version.
        $inner = new class implements VehicleEnquiry
        {
            public function lookup(string $registration): VehicleData
            {
                return VehicleData::fromApiResponse(['registrationNumber' => $registration, 'make' => 'FORD']);
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };

        $caching = new CachingVehicleEnquiryService($inner, Cache::store(), 3600, 'dvla-ves');

        $caching->lookup('AB12CDE');

        $cached = Cache::store()->get('dvla-ves:vehicle:AB12CDE');

        $this->assertIsArray($cached, 'Cache must hold the raw response array, never a VehicleData object');
        $this->assertSame('FORD', $cached['make']);
    }

    public function test_round_trip_survives_a_class_blocking_unserialize_store(): void
    {
        // Simulate the L13 hardened store: a cache repository whose underlying
        // store blocks object deserialization. If the decorator cached an
        // object this would return an incomplete class; caching an array
        // keeps the round-trip intact.
        $store = new \Illuminate\Cache\Repository(
            new class extends \Illuminate\Cache\ArrayStore
            {
                public function get($key)
                {
                    $value = parent::get($key);

                    // Re-encode through unserialize with allowed_classes:false,
                    // mirroring Laravel 13's serializable_classes=false default.
                    return unserialize(serialize($value), ['allowed_classes' => false]);
                }
            }
        );

        $inner = new class implements VehicleEnquiry
        {
            public function lookup(string $registration): VehicleData
            {
                return VehicleData::fromApiResponse(['registrationNumber' => $registration, 'make' => 'TESLA']);
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };

        $caching = new CachingVehicleEnquiryService($inner, $store, 3600, 'dvla-ves');

        $caching->lookup('AB12CDE');
        $second = $caching->lookup('AB12CDE');

        $this->assertInstanceOf(VehicleData::class, $second);
        $this->assertSame('TESLA', $second->make);
    }

    public function test_cached_entry_expires_after_the_configured_ttl(): void
    {
        $inner = new class implements VehicleEnquiry
        {
            public int $calls = 0;

            public function lookup(string $registration): VehicleData
            {
                $this->calls++;

                return VehicleData::fromApiResponse(['registrationNumber' => $registration]);
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };

        $ttl = 600;
        $caching = new CachingVehicleEnquiryService($inner, Cache::store(), $ttl, 'dvla-ves');

        Carbon::setTestNow('2026-06-13 12:00:00');
        $caching->lookup('AB12CDE');

        Carbon::setTestNow('2026-06-13 12:09:00'); // +9 min, within the 10-min TTL
        $caching->lookup('AB12CDE');
        $this->assertSame(1, $inner->calls, 'Within TTL the cache must serve, not the inner service');

        Carbon::setTestNow('2026-06-13 12:11:00'); // +11 min, past the 10-min TTL
        $caching->lookup('AB12CDE');
        $this->assertSame(2, $inner->calls, 'Past TTL the entry expires and the inner service is hit again');

        Carbon::setTestNow();
    }
}
