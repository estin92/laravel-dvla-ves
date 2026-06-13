<?php

namespace Estin92\DvlaVes\Exceptions;

class RateLimitExceededException extends DvlaVesException
{
    /** Seconds to wait before retrying, from the Retry-After header (null if absent). */
    public readonly ?int $retryAfter;

    public function __construct(?string $retryAfter = null)
    {
        $this->retryAfter = is_numeric($retryAfter) ? (int) $retryAfter : null;

        $message = 'Rate limit exceeded for DVLA VES API';

        if ($this->retryAfter !== null) {
            $message .= ". Retry after: {$this->retryAfter}s";
        }

        parent::__construct(
            message: $message,
            errorCode: 'RATE_LIMIT_EXCEEDED',
            code: 429,
        );
    }
}
