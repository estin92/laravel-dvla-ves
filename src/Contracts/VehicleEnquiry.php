<?php

namespace Estin92\DvlaVes\Contracts;

use Estin92\DvlaVes\Data\VehicleData;
use Estin92\DvlaVes\Exceptions\DvlaVesException;
use Estin92\DvlaVes\Exceptions\InvalidRegistrationException;
use Estin92\DvlaVes\Exceptions\RateLimitExceededException;
use Estin92\DvlaVes\Exceptions\ServiceUnavailableException;
use Estin92\DvlaVes\Exceptions\VehicleNotFoundException;

interface VehicleEnquiry
{
    /**
     * @throws DvlaVesException
     * @throws VehicleNotFoundException
     * @throws InvalidRegistrationException
     * @throws RateLimitExceededException
     * @throws ServiceUnavailableException
     */
    public function lookup(string $registration): VehicleData;

    public function isConfigured(): bool;
}
