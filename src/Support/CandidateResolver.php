<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Support;

use Illuminate\Http\Request;
use ParticleAcademy\LaravelJobs\Exceptions\CandidateNotResolvedException;

/**
 * Resolves the candidate's user id for the current request.
 *
 * The authenticated user, and nothing else unless a host explicitly opts in.
 *
 * ---------------------------------------------------------------------------
 * Why the fallback defaults to OFF (changed in 0.4.0)
 * ---------------------------------------------------------------------------
 *
 * This mirrored laravel-courses' LearnerResolver: prefer `$request->user()`, and
 * fall back to `user_id` from the request or an `X-Candidate-Id` header "for
 * server-to-server callers and tests". That fallback was gated on
 * `laravel-jobs.allow_input_user_id`, which **defaulted to true and was not in
 * the published config file** — so a host could not switch off an option it had
 * never been shown.
 *
 * The candidate routes mount `['api']` and nothing more, because the package
 * cannot assume the host added `auth`. So on a default install every ownership
 * check downstream — `forCandidate($resolved)`, and withdraw's
 * `$application->user_id !== $candidateId` — compared the record against an
 * identity THE CALLER SUPPLIED. That is not an authorization check. It is a check
 * that the attacker filled the form in consistently.
 *
 * Measured against 0.3.0, unauthenticated:
 *
 *     GET  /api/jobs/my-applications?user_id=7
 *          -> 200, that candidate's applications, with resume_path,
 *             cover_letter, contact_email and contact_phone
 *     POST /api/jobs/applications/3/withdraw   {"user_id": 7}
 *          -> 200, and the application is withdrawn
 *
 * An authenticated user cannot reach it — `$request->user()` wins — so this only
 * ever exposed hosts that had NOT put `auth` on the routes, which is exactly the
 * set of hosts relying on the package to be safe by default.
 *
 * The capability still exists for the case it was written for. It is now
 * something a host turns on, having decided who can reach the route.
 */
class CandidateResolver
{
    public function resolve(Request $request): int|string
    {
        $user = $request->user();

        if ($user !== null) {
            return $user->getAuthIdentifier();
        }

        // Default FALSE. A caller-supplied identity is trusted only where the host
        // has said so in its own config.
        if ((bool) config('laravel-jobs.allow_input_user_id', false)) {
            $explicit = $request->input('user_id') ?? $request->header('X-Candidate-Id');

            if ($explicit !== null && $explicit !== '') {
                return is_numeric($explicit) ? (int) $explicit : (string) $explicit;
            }
        }

        // Says nothing about how to become someone else. The previous message —
        // "Authenticate the request or supply user_id" — told an unauthenticated
        // caller precisely how to spoof a candidate, in the response body.
        throw new CandidateNotResolvedException(
            'Unable to resolve the candidate for this request.',
        );
    }

    public function resolveOrNull(Request $request): int|string|null
    {
        try {
            return $this->resolve($request);
        } catch (CandidateNotResolvedException) {
            return null;
        }
    }
}
