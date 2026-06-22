<?php

namespace Estin92\DvlaVes\Exceptions;

use Estin92\DvlaVes\Data\VesError;

class InvalidRegistrationException extends DvlaVesException
{
    public static function fromError(string $registration, VesError $error, ?string $correlationId = null): self
    {
        $message = "Invalid registration number: {$registration}. {$error->reason()}";

        return new self(
            message: $message,
            errorCode: $error->code ?? 'INVALID_REGISTRATION',
            errors: $error->errors,
            code: 400,
            correlationId: $correlationId,
        );
    }
}
