<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Exceptions;

class EmployerNotApprovedException extends JobsException
{
    public function status(): int
    {
        return 403;
    }
}
