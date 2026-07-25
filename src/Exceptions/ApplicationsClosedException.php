<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Exceptions;

class ApplicationsClosedException extends JobsException
{
    public function status(): int
    {
        return 422;
    }
}
