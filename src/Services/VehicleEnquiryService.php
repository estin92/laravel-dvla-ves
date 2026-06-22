<?php

namespace Estin92\DvlaVes\Services;

use Estin92\DvlaVes\Contracts\VehicleEnquiry;
use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Data\VesError;
use Estin92\DvlaVes\Exceptions\DvlaVesAuthorisationException;
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

    private ?string $correlationId = null;

    private readonly string $baseUrl;

    public function __construct(
        private readonly ?string $apiKey,
        string $baseUrl,
        private readonly int $timeout,
        private readonly int $retryAttempts,
        private readonly int $retryDelayMs,
    ) {
        $this->baseUrl = $this->forceHttps($baseUrl);
    }

    /**
     * The base URL is env-overridable; upgrade a plain http:// override to https
     * and reject any other scheme, so the API key can never go out over http.
     */
    private function forceHttps(string $baseUrl): string
    {
        $trimmed = trim($baseUrl);

        if (Str::startsWith($trimmed, 'https://')) {
            return $trimmed;
        }

        if (Str::startsWith($trimmed, 'http://')) {
            return Str::replaceStart('http://', 'https://', $trimmed);
        }

        throw new DvlaVesException("DVLA VES base URL must use HTTPS, got: {$baseUrl}");
    }

    /**
     * Set the X-Correlation-Id for the next lookup only.
     * The package will otherwise auto-generate a UUID in lookup().
     */
    public function setCorrelationId(?string $correlationId): static
    {
        $this->correlationId = $correlationId;

        return $this;
    }

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

        $correlationId = $this->correlationId ?? (string) Str::uuid();

        Log::debug('DVLA VES API: Looking up vehicle', [
            'registration' => $normalised,
            'correlationId' => $correlationId,
        ]);

        try {
            $response = $this->makeRequest($normalised, $correlationId);

            return $this->handleResponse($response, $normalised, $correlationId);
        } finally {
            $this->correlationId = null;
        }
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
    private function makeRequest(string $registration, string $correlationId): Response
    {
        $url = rtrim($this->baseUrl, '/').self::ENDPOINT;

        try {
            return Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'X-Correlation-Id' => $correlationId,
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
            throw new ServiceUnavailableException(previous: $e, correlationId: $correlationId);
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

        // retry() only passes connection/request exceptions; this is unreachable.
        return false; // @codeCoverageIgnore
    }

    /**
     * @throws DvlaVesException
     * @throws VehicleNotFoundException
     * @throws InvalidRegistrationException
     * @throws RateLimitExceededException
     * @throws ServiceUnavailableException
     */
    private function handleResponse(Response $response, string $registration, string $correlationId): VehicleData
    {
        $statusCode = $response->status();
        $body = $response->json();

        Log::debug('DVLA VES API: Response received', [
            'registration' => $registration,
            'status' => $statusCode,
            'correlationId' => $correlationId,
        ]);

        $this->logResponseForDebugging($response, $registration);

        if ($response->successful()) {
            if (! is_array($body)) {
                throw new DvlaVesException(
                    "DVLA VES returned a {$statusCode} with an empty or non-JSON body for registration: {$registration}",
                    correlationId: $correlationId,
                );
            }

            return VehicleData::fromApiResponse($body);
        }

        $this->handleErrorResponse($statusCode, $body, $registration, $response, $correlationId);
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
    private function handleErrorResponse(int $statusCode, ?array $body, string $registration, Response $response, string $correlationId): never
    {
        Log::warning('DVLA VES API: Error response', [
            'registration' => $registration,
            'status' => $statusCode,
            'body' => $body,
            'correlationId' => $correlationId,
        ]);

        $error = VesError::fromResponse($statusCode, $body);

        match ($statusCode) {
            400 => throw InvalidRegistrationException::fromError($registration, $error, $correlationId),
            403 => throw new DvlaVesAuthorisationException($error, $correlationId),
            404 => throw new VehicleNotFoundException($registration, $correlationId),
            429 => throw new RateLimitExceededException($response->header('Retry-After'), $correlationId),
            500, 502, 503, 504 => throw new ServiceUnavailableException($statusCode, correlationId: $correlationId),
            default => throw DvlaVesException::fromResponse($statusCode, $body, $correlationId),
        };
    }
}
