<?php

namespace App\Exceptions;

/**
 * User has already completed onboarding.
 * @message Onboarding is already completed.
 */
class OnboardingCompletedException extends ServerErrorException
{
    public function __construct()
    {
        parent::__construct('Onboarding is already completed.');
    }

    public function getStatusCode(): int
    {
        return 409;
    }

    public function getErrorCode(): string
    {
        return 'ONBOARDING_ALREADY_COMPLETED';
    }
}
