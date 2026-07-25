<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Base for every domain refusal this package raises.
 *
 * Each carries the HTTP status the API layer should answer with, so callers get
 * a meaningful code rather than a blanket 500. Laravel calls render() on a
 * thrown exception if it defines one, so no host-side handler wiring is needed.
 */
abstract class JobsException extends RuntimeException
{
    abstract public function status(): int;

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json([
            'message' => $this->getMessage(),
        ], $this->status());
    }
}
