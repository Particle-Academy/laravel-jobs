<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Fixtures;

use Illuminate\Http\Request;
use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;

/** Stands in for a host binding that has already checked ownership. */
class AllowAllEmployerAuthorizer implements AuthorizesEmployers
{
    public function allows(Request $request, int|string $employerId): bool
    {
        return true;
    }
}
