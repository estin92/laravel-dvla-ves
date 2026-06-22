<?php

namespace Estin92\DvlaVes\Exceptions;

use Estin92\DvlaVes\Data\VesError;
use Exception;
use Throwable;

class DvlaVesException extends Exception
{
    /**
     * @param  list<array{status?: string, code?: string, title?: string, detail?: string}>|null  $errors
     */
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?array $errors = null,
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $correlationId = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    public static function fromResponse(int $statusCode, ?array $body, ?string $correlationId = null): self
    {
        $error = VesError::fromResponse($statusCode, $body);

        return new self(
            $error->reason(),
            $error->code,
            $error->errors,
            $statusCode,
            correlationId: $correlationId,
        );
    }
}
