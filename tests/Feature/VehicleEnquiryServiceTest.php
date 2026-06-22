<?php

namespace Estin92\DvlaVes\Tests\Feature;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Enums\FuelType;
use Estin92\DvlaVes\Enums\TaxStatus;
use Estin92\DvlaVes\Exceptions\DvlaVesAuthorisationException;
use Estin92\DvlaVes\Exceptions\DvlaVesException;
use Estin92\DvlaVes\Exceptions\InvalidRegistrationException;
use Estin92\DvlaVes\Exceptions\RateLimitExceededException;
use Estin92\DvlaVes\Exceptions\ServiceUnavailableException;
use Estin92\DvlaVes\Exceptions\VehicleNotFoundException;
use Estin92\DvlaVes\Facades\DvlaVes;
use Estin92\DvlaVes\Services\VehicleEnquiryService;
use Estin92\DvlaVes\Tests\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;

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
    #[DataProvider('dvlaWireFuelTypeProvider')]
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
    #[DataProvider('dvlaWireTaxStatusProvider')]
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
                'errors' => [[
                    'status' => '400',
                    'code' => 'ENQ103',
                    'title' => 'Bad Request',
                    'detail' => 'Invalid format for field - vehicle registration number',
                ]],
            ], 400),
        ]);

        try {
            DvlaVes::lookup('INVALID!');
            $this->fail('Expected InvalidRegistrationException');
        } catch (InvalidRegistrationException $e) {
            $this->assertSame(400, $e->getCode());
            $this->assertSame('ENQ103', $e->errorCode);
            $this->assertStringContainsString('Invalid format for field', $e->getMessage());
            $this->assertStringContainsString('INVALID!', $e->getMessage());
        }
    }

    public function test_it_throws_authorisation_exception_for_403(): void
    {
        $body = json_decode(file_get_contents(__DIR__.'/../fixtures/ves-error-403.json'), true);

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($body, 403),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
            $this->fail('Expected DvlaVesAuthorisationException');
        } catch (DvlaVesAuthorisationException $e) {
            $this->assertSame(403, $e->getCode());
            $this->assertStringContainsString('Forbidden', $e->getMessage());
            $this->assertStringContainsString('DVLA_VES_API_KEY', $e->getMessage());
        }
    }

    public function test_it_does_not_retry_a_403(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response(['message' => 'Forbidden'], 403),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
        } catch (DvlaVesAuthorisationException) {
            // expected
        }

        Http::assertSentCount(1);
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
            throw new ConnectionException('cURL error 6: Could not resolve host');
        });

        try {
            DvlaVes::lookup('AA19AAA');
            $this->fail('Expected ServiceUnavailableException');
        } catch (ServiceUnavailableException $e) {
            $this->assertInstanceOf(ConnectionException::class, $e->getPrevious());
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

        Storage::shouldReceive('disk')
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
        Storage::fake('local');

        config([
            'dvla-ves.debug.log_responses' => true,
            'dvla-ves.debug.disk' => 'local',
            'dvla-ves.debug.path' => 'dvla-ves-debug',
        ]);

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        DvlaVes::lookup('aa 19 aaa');

        Storage::disk('local')->assertExists('dvla-ves-debug/AA19AAA.json');
    }

    public function test_debug_write_does_nothing_when_disabled(): void
    {
        Storage::fake('local');

        config([
            'dvla-ves.debug.log_responses' => false,
            'dvla-ves.debug.disk' => 'local',
            'dvla-ves.debug.path' => 'dvla-ves-debug',
        ]);

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        DvlaVes::lookup('AA19AAA');

        Storage::disk('local')->assertDirectoryEmpty('dvla-ves-debug');
    }

    public function test_debug_write_skips_when_enabled_but_body_is_empty(): void
    {
        Storage::fake('local');

        config([
            'dvla-ves.debug.log_responses' => true,
            'dvla-ves.debug.disk' => 'local',
            'dvla-ves.debug.path' => 'dvla-ves-debug',
        ]);

        // A 200 with an empty body: the debug dump has nothing to write and must
        // not create a file. (The lookup itself still raises a domain exception.)
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response('', 200),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
        } catch (DvlaVesException) {
            // expected: empty body is not a valid vehicle payload
        }

        Storage::disk('local')->assertDirectoryEmpty('dvla-ves-debug');
    }

    public function test_debug_write_skips_when_registration_sanitises_to_empty(): void
    {
        Storage::fake('local');

        config([
            'dvla-ves.debug.log_responses' => true,
            'dvla-ves.debug.disk' => 'local',
            'dvla-ves.debug.path' => 'dvla-ves-debug',
        ]);

        // A registration of only non-alphanumeric characters sanitises to an
        // empty filename; the dump must be skipped rather than write a file
        // named for an empty string (also guards against path-traversal input).
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        DvlaVes::lookup('/././');

        Storage::disk('local')->assertDirectoryEmpty('dvla-ves-debug');
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

    public function test_it_auto_generates_a_correlation_id_header_when_none_is_supplied(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        DvlaVes::lookup('AA19AAA');

        Http::assertSent(function ($request) {
            $sent = $request->header('X-Correlation-Id')[0] ?? null;

            return is_string($sent) && $sent !== '';
        });
    }

    public function test_set_correlation_id_sends_the_supplied_value_verbatim(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        $this->dvlaService()->setCorrelationId('caller-trace-123')->lookup('AA19AAA');

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Correlation-Id', 'caller-trace-123');
        });
    }

    public function test_a_supplied_correlation_id_is_consumed_once_then_resets_to_auto(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        $service = $this->dvlaService();
        $service->setCorrelationId('caller-trace-123')->lookup('AA19AAA');
        $service->lookup('AA19AAA');

        $sentIds = Http::recorded()
            ->map(fn ($pair) => $pair[0]->header('X-Correlation-Id')[0] ?? null)
            ->all();

        $this->assertSame('caller-trace-123', $sentIds[0], 'The first lookup must use the supplied id');
        $this->assertNotSame('caller-trace-123', $sentIds[1], 'The second lookup must not reuse the consumed id');
        $this->assertIsString($sentIds[1]);
        $this->assertNotSame('', $sentIds[1], 'The second lookup must fall back to an auto-generated id');
    }

    public function test_a_supplied_correlation_id_is_readable_on_a_thrown_exception(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], 503),
        ]);

        try {
            $this->dvlaService()->setCorrelationId('caller-trace-123')->lookup('AA19AAA');
            $this->fail('Expected ServiceUnavailableException');
        } catch (ServiceUnavailableException $e) {
            $this->assertSame('caller-trace-123', $e->correlationId);
        }
    }

    public function test_an_auto_generated_correlation_id_is_readable_on_a_thrown_exception(): void
    {
        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([
                'message' => 'Vehicle not found',
            ], 404),
        ]);

        try {
            DvlaVes::lookup('NOTFOUND');
            $this->fail('Expected VehicleNotFoundException');
        } catch (VehicleNotFoundException $e) {
            $sentId = Http::recorded()->first()[0]->header('X-Correlation-Id')[0] ?? null;

            $this->assertIsString($e->correlationId);
            $this->assertNotSame('', $e->correlationId);
            $this->assertSame($sentId, $e->correlationId, 'The exception must carry the id that was actually sent');
        }
    }

    public function test_the_correlation_id_is_recorded_in_the_error_log_context(): void
    {
        Log::spy();

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response([], 503),
        ]);

        try {
            $this->dvlaService()->setCorrelationId('caller-trace-123')->lookup('AA19AAA');
        } catch (ServiceUnavailableException) {
            // expected; we are asserting on the log context, not the exception here
        }

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => ($context['correlationId'] ?? null) === 'caller-trace-123')
            ->atLeast()->once();
    }

    public function test_the_debug_dump_remains_the_bare_dvla_response_body(): void
    {
        Storage::fake('local');

        config([
            'dvla-ves.debug.log_responses' => true,
            'dvla-ves.debug.disk' => 'local',
            'dvla-ves.debug.path' => 'dvla-ves-debug',
        ]);

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        $this->dvlaService()->setCorrelationId('caller-trace-123')->lookup('AA19AAA');

        $disk = config('dvla-ves.debug.disk');
        $path = config('dvla-ves.debug.path');
        $dump = json_decode(Storage::disk($disk)->get("{$path}/AA19AAA.json"), true);

        $this->assertSame($this->getSampleApiResponse(), $dump, 'The dump must remain the verbatim DVLA body with no injected metadata');
        $this->assertArrayNotHasKey('correlationId', $dump, 'Correlation metadata must not pollute the captured response payload');
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
                throw new ConnectionException('cURL error 28: timeout');
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

            throw new ConnectionException('cURL error 6: Could not resolve host');
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

    #[DataProvider('gatewayErrorStatusProvider')]
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
        $errorBody = json_decode(file_get_contents(__DIR__.'/../fixtures/ves-error.json'), true);

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($errorBody, 451),
        ]);

        try {
            DvlaVes::lookup('AA19AAA');
            $this->fail('Expected DvlaVesException');
        } catch (DvlaVesException $e) {
            $this->assertSame(451, $e->getCode());
            $this->assertSame('400', $e->errorCode);
            $this->assertSame($errorBody['errors'], $e->errors);
        }
    }

    public function test_lookup_normalisation_hits_the_same_cache_entry(): void
    {
        config(['dvla-ves.cache.enabled' => true]);
        $this->app->forgetInstance(VehicleEnquiry::class);
        $this->app->forgetInstance('dvla-ves');

        Http::fake([
            '*/vehicle-enquiry/v1/vehicles' => Http::response($this->getSampleApiResponse(), 200),
        ]);

        DvlaVes::lookup('AA19AAA');
        DvlaVes::lookup('aa 19 aaa'); // normalises to AA19AAA -> cache hit

        Http::assertSentCount(1);
    }

    private function dvlaService(): VehicleEnquiryService
    {
        return $this->app->make(VehicleEnquiryService::class);
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
            'euroStatus' => 'Euro 6 AG',
            'markedForExport' => false,
        ];
    }
}
