<?php

namespace Estin92\DvlaVes\Exceptions;

use Estin92\DvlaVes\Data\VesError;

/**
 * Thrown on an HTTP 403, which DVLA returns for a rejected, missing, or
 * unentitled API key — the message points at the key as the likely cause.
 */
class DvlaVesAuthorisationException extends DvlaVesException
{
    public function __construct(VesError $error, ?string $correlationId = null)
    {
        parent::__construct(
            message: "DVLA VES rejected the request (403 {$error->reason()}). Check the DVLA_VES_API_KEY is set, valid, and entitled for this environment.",
            errorCode: $error->code ?? 'AUTHORISATION_FAILED',
            errors: $error->errors,
            code: 403,
            correlationId: $correlationId,
        );
    }
}
