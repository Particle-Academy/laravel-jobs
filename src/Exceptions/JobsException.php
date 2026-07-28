<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Base for every domain refusal this package raises.
 *
 * Implements HttpExceptionInterface so the status applies however the request
 * was made. `render()` alone was not enough: it returns null for a non-JSON
 * request, which hands the exception back to Laravel, which has no reason to
 * treat a plain RuntimeException as anything but a 500. A browser hitting the
 * same endpoint therefore got a server error where an API client got a correct
 * 401/402/403.
 *
 * With the interface, Laravel maps the status for both, and `render()` only
 * adds the richer JSON body.
 */
abstract class JobsException extends RuntimeException implements HttpExceptionInterface
{
    abstract public function status(): int;

    public function getStatusCode(): int
    {
        return $this->status();
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [];
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            // Not an error path — returning null lets Laravel render its normal
            // error page, now with the right status thanks to the interface.
            return null;
        }

        return response()->json([
            'message' => $this->getMessage(),
        ], $this->status());
    }
}
