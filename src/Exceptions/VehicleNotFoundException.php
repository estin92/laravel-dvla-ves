<?php

namespace Estin92\DvlaVes\Exceptions;

class VehicleNotFoundException extends DvlaVesException
{
    public function __construct(string $registration, ?string $correlationId = null)
    {
        parent::__construct(
            message: "Vehicle not found for registration: {$registration}",
            errorCode: 'VEHICLE_NOT_FOUND',
            code: 404,
            correlationId: $correlationId,
        );
    }
}
