<?php

namespace Estin92\DvlaVes\Exceptions;

class InvalidRegistrationException extends DvlaVesException
{
    public function __construct(string $registration, ?string $reason = null, ?string $correlationId = null)
    {
        $message = "Invalid registration number: {$registration}";

        if ($reason) {
            $message .= ". {$reason}";
        }

        parent::__construct(
            message: $message,
            errorCode: 'INVALID_REGISTRATION',
            code: 400,
            correlationId: $correlationId,
        );
    }
}
