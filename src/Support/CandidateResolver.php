<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Support;

use Illuminate\Http\Request;
use RuntimeException;

/**
 * Resolves the candidate's user id for the current request.
 *
 * Mirrors laravel-courses' LearnerResolver: prefer the authenticated user, and
 * fall back to an explicit id for server-to-server callers and tests. Hosts can
 * switch the fallback off.
 */
class CandidateResolver
{
    public function resolve(Request $request): int|string
    {
        $user = $request->user();

        if ($user !== null) {
            return $user->getAuthIdentifier();
        }

        if (config('laravel-jobs.allow_input_user_id', true)) {
            $explicit = $request->input('user_id') ?? $request->header('X-Candidate-Id');

            if ($explicit !== null && $explicit !== '') {
                return is_numeric($explicit) ? (int) $explicit : (string) $explicit;
            }
        }

        throw new RuntimeException(
            'Unable to resolve candidate. Authenticate the request or supply user_id.',
        );
    }

    public function resolveOrNull(Request $request): int|string|null
    {
        try {
            return $this->resolve($request);
        } catch (RuntimeException) {
            return null;
        }
    }
}
