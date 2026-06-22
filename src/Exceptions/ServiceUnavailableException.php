<?php

namespace Estin92\DvlaVes\Exceptions;

use Throwable;

class ServiceUnavailableException extends DvlaVesException
{
    public function __construct(int $statusCode = 503, ?Throwable $previous = null, ?string $correlationId = null)
    {
        parent::__construct(
            message: 'DVLA VES API is currently unavailable',
            errorCode: 'SERVICE_UNAVAILABLE',
            code: $statusCode,
            previous: $previous,
            correlationId: $correlationId,
        );
    }
}
