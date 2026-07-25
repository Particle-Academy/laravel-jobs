<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Exceptions;

class DuplicateApplicationException extends JobsException
{
    public function status(): int
    {
        return 409;
    }
}
