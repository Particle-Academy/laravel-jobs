<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Exceptions;

/**
 * The request did not identify a candidate.
 *
 * 401, not 500: "you are not signed in" is an ordinary answer to an anonymous
 * request, and the candidate endpoints are reachable unauthenticated by design
 * — the package cannot assume the host mounted them behind `auth`.
 */
class CandidateNotResolvedException extends JobsException
{
    public function status(): int
    {
        return 401;
    }
}
