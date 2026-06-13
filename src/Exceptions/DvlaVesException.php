<?php

namespace Estin92\DvlaVes\Exceptions;

use Exception;
use Throwable;

class DvlaVesException extends Exception
{
    public function __construct(
        string $message,
        public readonly ?string $errorCode = null,
        public readonly ?array $errors = null,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromResponse(int $statusCode, ?array $body): self
    {
        $body ??= [];
        $message = $body['message'] ?? 'An unknown error occurred';
        $errorCode = $body['errors'][0]['code'] ?? null;
        $errors = $body['errors'] ?? null;

        return new self($message, $errorCode, $errors, $statusCode);
    }
}
