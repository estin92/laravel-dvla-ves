<?php

namespace Estin92\DvlaVes\Services;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Exceptions\DvlaVesException;
use Estin92\DvlaVes\Exceptions\InvalidRegistrationException;
use Estin92\DvlaVes\Exceptions\RateLimitExceededException;
use Estin92\DvlaVes\Exceptions\ServiceUnavailableException;
use Estin92\DvlaVes\Exceptions\VehicleNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class VehicleEnquiryService implements VehicleEnquiry
{
    private const ENDPOINT = '/vehicle-enquiry/v1/vehicles';

    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl,
        private readonly int $timeout,
        private readonly int $retryAttempts,
        private readonly int $retryDelayMs,
    ) {}

    /**
     * Look up vehicle details by registration number.
     *
     * @throws DvlaVesException
     * @throws VehicleNotFoundException
     * @throws InvalidRegistrationException
     * @throws RateLimitExceededException
     * @throws ServiceUnavailableException
     */
    public function lookup(string $registration): VehicleData
    {
        $normalised = $this->normaliseRegistration($registration);

        $this->validateConfiguration();

        Log::debug('DVLA VES API: Looking up vehicle', ['registration' => $normalised]);

        $response = $this->makeRequest($normalised);

        return $this->handleResponse($response, $normalised);
    }

    /**
     * Check if the service is configured and enabled.
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) && config('dvla-ves.enabled', true);
    }

    private function normaliseRegistration(string $registration): string
    {
        return Str::of($registration)->upper()->replace(' ', '')->toString();
    }

    private function validateConfiguration(): void
    {
        if (! config('dvla-ves.enabled', true)) {
            throw new DvlaVesException('DVLA VES API is disabled');
        }

        if (empty($this->apiKey)) {
            throw new DvlaVesException('DVLA VES API key is not configured');
        }
    }

    /**
     * @throws ServiceUnavailableException when the API is unreachable after retries
     */
    private function makeRequest(string $registration): Response
    {
        $url = rtrim($this->baseUrl, '/').self::ENDPOINT;

        try {
            return Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
            ])
                ->timeout($this->timeout)
                ->retry(
                    times: $this->retryAttempts,
                    sleepMilliseconds: $this->retryDelayMs,
                    when: fn (Throwable $exception) => $this->shouldRetry($exception),
                    throw: false,
                )
                ->post($url, [
                    'registrationNumber' => $registration,
                ]);
        } catch (ConnectionException $e) {
            throw new ServiceUnavailableException(previous: $e);
        }
    }

    private function shouldRetry(Throwable $exception): bool
    {
        if ($exception instanceof ConnectionException) {
            return true;
        }

        if ($exception instanceof RequestException) {
            return in_array($exception->response?->status(), [500, 502, 503, 504], true);
        }

        return false;
    }

    /**
     * @throws DvlaVesException
     * @throws VehicleNotFoundException
     * @throws InvalidRegistrationException
     * @throws RateLimitExceededException
     * @throws ServiceUnavailableException
     */
    private function handleResponse(Response $response, string $registration): VehicleData
    {
        $statusCode = $response->status();
        $body = $response->json();

        Log::debug('DVLA VES API: Response received', [
            'registration' => $registration,
            'status' => $statusCode,
        ]);

        $this->logResponseForDebugging($response, $registration);

        if ($response->successful()) {
            if (! is_array($body)) {
                throw new DvlaVesException(
                    "DVLA VES returned a {$statusCode} with an empty or non-JSON body for registration: {$registration}"
                );
            }

            return VehicleData::fromApiResponse($body);
        }

        $this->handleErrorResponse($statusCode, $body, $registration, $response);
    }

    private function logResponseForDebugging(Response $response, string $registration): void
    {
        if (! config('dvla-ves.debug.log_responses')) {
            return;
        }

        $body = $response->json();

        if (! $body) {
            return;
        }

        $safeName = preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($registration));

        if ($safeName === '') {
            return;
        }

        $disk = config('dvla-ves.debug.disk');
        $path = config('dvla-ves.debug.path');

        // Best-effort: a disk failure must never turn a successful lookup into
        // an error. The dump is an opt-in diagnostic, not part of the contract.
        try {
            Storage::disk($disk)->put(
                "{$path}/{$safeName}.json",
                json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        } catch (Throwable $e) {
            Log::warning('DVLA VES API: failed to write debug response', [
                'registration' => $registration,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @throws DvlaVesException
     * @throws VehicleNotFoundException
     * @throws InvalidRegistrationException
     * @throws RateLimitExceededException
     * @throws ServiceUnavailableException
     */
    private function handleErrorResponse(int $statusCode, ?array $body, string $registration, Response $response): never
    {
        Log::warning('DVLA VES API: Error response', [
            'registration' => $registration,
            'status' => $statusCode,
            'body' => $body,
        ]);

        match ($statusCode) {
            400 => throw new InvalidRegistrationException($registration, $body['message'] ?? null),
            404 => throw new VehicleNotFoundException($registration),
            429 => throw new RateLimitExceededException($response->header('Retry-After')),
            500, 502, 503, 504 => throw new ServiceUnavailableException($statusCode),
            default => throw DvlaVesException::fromResponse($statusCode, $body),
        };
    }
}
