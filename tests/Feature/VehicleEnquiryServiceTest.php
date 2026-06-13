<?php

namespace Estin92\DvlaVes\Tests\Feature;

use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Enums\FuelType;
use Estin92\DvlaVes\Enums\TaxStatus;
use Estin92\DvlaVes\Exceptions\DvlaVesException;
use Estin92\DvlaVes\Exceptions\InvalidRegistrationException;
use Estin92\DvlaVes\Exceptions\RateLimitExceededException;
use Estin92\DvlaVes\Exceptions\ServiceUnavailableException;
use Estin92\DvlaVes\Exceptions\VehicleNotFoundException;
use Estin92\DvlaVes\Facades\DvlaVes;
use Estin92\DvlaVes\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class VehicleEnquiryServiceTest extends TestCase
{
    public function test_it_can_lookup_vehicle_by_registration(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        $result = DvlaVes::lookup('AA19AAA');

        $this->assertInstanceOf(VehicleData::class, $result);
        $this->assertSame('AA19AAA', $result->registrationNumber);
        $this->assertSame('FORD', $result->make);
    }

    /**
     * @return array<string, array{0: string, 1: FuelType}>
     */
    public static function dvlaWireFuelTypeProvider(): array
    {
        return [
            'PETROL' => ['PETROL', FuelType::Petrol],
            'DIESEL' => ['DIESEL', FuelType::Diesel],
            'ELECTRICITY' => ['ELECTRICITY', FuelType::Electricity],
            'HYBRID ELECTRIC' => ['HYBRID ELECTRIC', FuelType::HybridElectric],
            'ELECTRIC DIESEL' => ['ELECTRIC DIESEL', FuelType::ElectricDiesel],
            'GAS BI-FUEL' => ['GAS BI-FUEL', FuelType::GasBiFuel],
            'unknown coerces to Other' => ['SOMETHING UNSEEN', FuelType::Other],
        ];
    }

    /**
     * Orchestration test: when DVLA returns a fuelType wire value, the
     * service must produce a VehicleData with the typed FuelType set.
     * Covers every value we have evidence DVLA returns plus the future-
     * proofing path where unknown values coerce to Other.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dvlaWireFuelTypeProvider')]
    public function test_it_parses_dvla_fuel_type_wire_values_into_typed_enum(string $wireValue, FuelType $expected): void
    {
        $response = $this->getSampleApiResponse();
        $response['fuelType'] = $wireValue;

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($response, 200),
        ]);

        $result = DvlaVes::lookup('AA19AAA');

        $this->assertSame($expected, $result->fuelType);
        $this->assertSame(
            $wireValue,
            $result->rawResponse['fuelType'] ?? null,
            'rawResponse must preserve the verbatim wire value',
        );
    }

    /**
     * @return array<string, array{0: string, 1: TaxStatus}>
     */
    public static function dvlaWireTaxStatusProvider(): array
    {
        return [
            'Taxed' => ['Taxed', TaxStatus::Taxed],
            'Untaxed' => ['Untaxed', TaxStatus::Untaxed],
            'SORN' => ['SORN', TaxStatus::Sorn],
            'Not Taxed for on Road Use' => ['Not Taxed for on Road Use', TaxStatus::NotTaxable],
        ];
    }

    /**
     * Orchestration: every documented DVLA taxStatus wire value parses into
     * the typed TaxStatus on VehicleData AND is preserved verbatim on
     * rawResponse so the consumer can persist both.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('dvlaWireTaxStatusProvider')]
    public function test_it_parses_dvla_tax_status_wire_values_into_typed_enum(string $wireValue, TaxStatus $expected): void
    {
        $response = $this->getSampleApiResponse();
        $response['taxStatus'] = $wireValue;

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($response, 200),
        ]);

        $result = DvlaVes::lookup('AA19AAA');

        $this->assertSame($expected, $result->taxStatus);
        $this->assertSame(
            $wireValue,
            $result->rawResponse['taxStatus'] ?? null,
            'rawResponse must preserve the verbatim wire value',
        );
    }

    public function test_it_normalises_registration_number(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        $result = DvlaVes::lookup('aa 19 aaa');

        Http::assertSent(function ($request) {
            return $request->data()['registrationNumber'] === 'AA19AAA';
        });

        $this->assertSame('AA19AAA', $result->registrationNumber);
    }

    public function test_it_throws_vehicle_not_found_exception_for_404(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([
                'message' => 'Vehicle not found',
            ], 404),
        ]);

        $this->expectException(VehicleNotFoundException::class);
        $this->expectExceptionMessage('Vehicle not found for registration: NOTFOUND');

        DvlaVes::lookup('NOTFOUND');
    }

    public function test_it_throws_invalid_registration_exception_for_400(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([
                'message' => 'Invalid registration number',
            ], 400),
        ]);

        $this->expectException(InvalidRegistrationException::class);

        DvlaVes::lookup('INVALID!');
    }

    public function test_it_throws_rate_limit_exception_for_429(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], 429, [
                'Retry-After' => '60',
            ]),
        ]);

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        DvlaVes::lookup('AA19AAA');
    }

    public function test_rate_limit_exception_exposes_retry_after_as_int(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], 429, ['Retry-After' => '60']),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertSame(60, $e->retryAfter);
        }
    }

    public function test_rate_limit_exception_retry_after_is_null_when_header_absent(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], 429),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
            $this->fail('Expected RateLimitExceededException');
        } catch (RateLimitExceededException $e) {
            $this->assertNull($e->retryAfter);
        }
    }

    public function test_it_throws_service_unavailable_exception_for_503(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], 503),
        ]);

        $this->expectException(ServiceUnavailableException::class);

        DvlaVes::lookup('AA19AAA');
    }

    public function test_it_throws_service_unavailable_exception_for_500(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], 500),
        ]);

        $this->expectException(ServiceUnavailableException::class);

        DvlaVes::lookup('AA19AAA');
    }

    public function test_persistent_connection_failure_is_wrapped_as_service_unavailable(): void
    {
        // A DNS/TLS/timeout failure that survives all retries throws a raw
        // ConnectionException. It must surface as a domain ServiceUnavailable,
        // not leak the framework exception to the caller.
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('cURL error 6: Could not resolve host');
        });

        try {
            DvlaVes::lookup('AA19AAA');
            $this->fail('Expected ServiceUnavailableException');
        } catch (ServiceUnavailableException $e) {
            $this->assertInstanceOf(\Illuminate\Http\Client\ConnectionException::class, $e->getPrevious());
        }
    }

    public function test_it_throws_exception_when_api_key_not_configured(): void
    {
        config(['dvla-ves.api_key' => null]);

        $this->app->forgetInstance('dvla-ves');

        $this->expectException(DvlaVesException::class);
        $this->expectExceptionMessage('API key is not configured');

        DvlaVes::lookup('AA19AAA');
    }

    public function test_it_throws_exception_when_disabled(): void
    {
        config(['dvla-ves.enabled' => false]);

        $this->app->forgetInstance('dvla-ves');

        $this->expectException(DvlaVesException::class);
        $this->expectExceptionMessage('disabled');

        DvlaVes::lookup('AA19AAA');
    }

    public function test_is_configured_returns_true_when_properly_configured(): void
    {
        $this->assertTrue(DvlaVes::isConfigured());
    }

    public function test_is_configured_returns_false_when_api_key_missing(): void
    {
        config(['dvla-ves.api_key' => null]);

        $this->app->forgetInstance('dvla-ves');

        $this->assertFalse(DvlaVes::isConfigured());
    }

    public function test_is_configured_returns_false_when_disabled(): void
    {
        config(['dvla-ves.enabled' => false]);

        $this->app->forgetInstance('dvla-ves');

        $this->assertFalse(DvlaVes::isConfigured());
    }

    public function test_debug_write_failure_does_not_fail_a_successful_lookup(): void
    {
        // The debug dump is an opt-in diagnostic; a disk failure must never
        // convert a successful lookup into an error.
        config([
            'dvla-ves.debug.log_responses' => true,
            'dvla-ves.debug.disk' => 'dvla-debug-broken',
        ]);

        \Illuminate\Support\Facades\Storage::shouldReceive('disk')
            ->andThrow(new \RuntimeException('disk is full'));

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        $result = DvlaVes::lookup('AA19AAA');

        $this->assertSame('AA19AAA', $result->registrationNumber);
        $this->assertSame('FORD', $result->make);
    }

    public function test_debug_write_persists_response_when_enabled(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        config([
            'dvla-ves.debug.log_responses' => true,
            'dvla-ves.debug.disk' => 'local',
            'dvla-ves.debug.path' => 'dvla-ves-debug',
        ]);

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        DvlaVes::lookup('aa 19 aaa');

        \Illuminate\Support\Facades\Storage::disk('local')->assertExists('dvla-ves-debug/AA19AAA.json');
    }

    public function test_debug_write_does_nothing_when_disabled(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');

        config([
            'dvla-ves.debug.log_responses' => false,
            'dvla-ves.debug.disk' => 'local',
            'dvla-ves.debug.path' => 'dvla-ves-debug',
        ]);

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        DvlaVes::lookup('AA19AAA');

        \Illuminate\Support\Facades\Storage::disk('local')->assertDirectoryEmpty('dvla-ves-debug');
    }

    public function test_it_sends_correct_headers(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        DvlaVes::lookup('AA19AAA');

        Http::assertSent(function ($request) {
            return $request->hasHeader('x-api-key', 'test-api-key')
                && $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_it_retries_a_503_then_succeeds(): void
    {
        Http::fakeSequence('*/vehicle-enquiry/v1/vehicles')
            ->push([], 503)
            ->push($this->getSampleApiResponse(), 200);

        $result = DvlaVes::lookup('AA19AAA');

        $this->assertSame('FORD', $result->make);
        Http::assertSentCount(2);
    }

    public function test_it_retries_a_connection_exception_then_succeeds(): void
    {
        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            if ($attempts === 1) {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timeout');
            }

            return Http::response($this->getSampleApiResponse(), 200);
        });

        $result = DvlaVes::lookup('AA19AAA');

        $this->assertSame('FORD', $result->make);
        $this->assertSame(2, $attempts, 'A transient connection failure must be retried exactly once before success');
    }

    public function test_persistent_5xx_exhausts_retries_then_throws_service_unavailable(): void
    {
        // Every attempt returns a 503. The retry arm must fire retry_attempts
        // times and then surface a domain ServiceUnavailableException, not a
        // raw HTTP response or framework exception.
        config(['dvla-ves.retry_attempts' => 3]);
        $this->app->forgetInstance('dvla-ves');

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], 503),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
            $this->fail('Expected ServiceUnavailableException after exhausting retries');
        } catch (ServiceUnavailableException $e) {
            $this->assertSame(503, $e->getCode());
        }

        Http::assertSentCount(3);
    }

    public function test_persistent_connection_failure_exhausts_retries(): void
    {
        config(['dvla-ves.retry_attempts' => 3]);
        $this->app->forgetInstance('dvla-ves');

        $attempts = 0;

        Http::fake(function () use (&$attempts) {
            $attempts++;

            throw new \Illuminate\Http\Client\ConnectionException('cURL error 6: Could not resolve host');
        });

        $this->expectException(ServiceUnavailableException::class);

        try {
            DvlaVes::lookup('AA19AAA');
        } finally {
            $this->assertSame(3, $attempts, 'A persistent connection failure must be attempted retry_attempts times');
        }
    }

    public function test_rate_limit_path_sends_exactly_one_request(): void
    {
        // A 429 is not in the retry set; the request must not be retried.
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], 429, ['Retry-After' => '60']),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
        } catch (RateLimitExceededException) {
            // expected
        }

        Http::assertSentCount(1);
    }

    public function test_empty_successful_body_throws_domain_exception_not_type_error(): void
    {
        // A 200/204 with an empty or non-JSON body makes Response::json() return
        // null. fromApiResponse(array) would then TypeError; the service must
        // raise a DvlaVesException instead.
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response('', 200),
        ]);

        $this->expectException(DvlaVesException::class);
        $this->expectExceptionMessage('empty or non-JSON body');

        DvlaVes::lookup('AA19AAA');
    }

    public function test_it_does_not_retry_a_400(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response(['message' => 'Invalid'], 400),
        ]);

        try {
            DvlaVes::lookup('INVALID!');
        } catch (InvalidRegistrationException) {
            // expected
        }

        Http::assertSentCount(1);
    }

    /**
     * @return array<string, array{0: int}>
     */
    public static function gatewayErrorStatusProvider(): array
    {
        return [
            '502 Bad Gateway' => [502],
            '504 Gateway Timeout' => [504],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('gatewayErrorStatusProvider')]
    public function test_it_throws_service_unavailable_for_gateway_errors(int $status): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], $status),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
            $this->fail("Expected ServiceUnavailableException for {$status}");
        } catch (ServiceUnavailableException $e) {
            $this->assertSame($status, $e->getCode());
        }
    }

    public function test_unmapped_status_throws_base_dvla_exception_with_error_body(): void
    {
        // The default match arm: e.g. a 403. Uses the shipped ves-error.json
        // shape to assert the error body is surfaced on the exception.
        $errorBody = json_decode(file_get_contents(__DIR__.'/../fixtures/ves-error.json'), true);

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($errorBody, 403),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
            $this->fail('Expected DvlaVesException');
        } catch (DvlaVesException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertSame('400', $e->errorCode);
            $this->assertSame($errorBody['errors'], $e->errors);
        }
    }

    public function test_lookup_normalisation_hits_the_same_cache_entry(): void
    {
        config(['dvla-ves.cache.enabled' => true]);
        $this->app->forgetInstance(\Estin92\DvlaVes\Contracts\VehicleEnquiry::class);
        $this->app->forgetInstance('dvla-ves');

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        DvlaVes::lookup('AA19AAA');
        DvlaVes::lookup('aa 19 aaa'); // normalises to AA19AAA -> cache hit

        Http::assertSentCount(1);
    }

    /**
     * @return array<string, mixed>
     */
    private function getSampleApiResponse(): array
    {
        return [
            'registrationNumber' => 'AA19AAA',
            'make' => 'FORD',
            'colour' => 'BLUE',
            'fuelType' => 'PETROL',
            'engineCapacity' => 1499,
            'co2Emissions' => 119,
            'taxStatus' => 'Taxed',
            'taxDueDate' => '2025-12-01',
            'motStatus' => 'Valid',
            'motExpiryDate' => '2025-03-15',
            'yearOfManufacture' => 2019,
            'monthOfFirstRegistration' => '2019-03',
            'euroStatus' => 'Euro 6',
            'markedForExport' => false,
        ];
    }
}
