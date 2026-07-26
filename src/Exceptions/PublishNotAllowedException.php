<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Exceptions;

use ParticleAcademy\LaravelJobs\Support\PublishDecision;

/**
 * Raised when the publish gate refuses.
 *
 * Carries the whole decision, so a caller can branch on `code` (e.g. send the
 * employer to `meta['checkout_url']` when payment is required) rather than
 * pattern-matching a message.
 */
class PublishNotAllowedException extends JobsException
{
    public function __construct(public readonly PublishDecision $decision)
    {
        parent::__construct($decision->reason ?? 'This posting may not be published.');
    }

    public function status(): int
    {
        // 402 is the honest code when the only thing missing is money.
        return $this->decision->code === 'payment_required' ? 402 : 403;
    }

    public function render($request): ?\Illuminate\Http\JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json(array_filter([
            'message' => $this->getMessage(),
            'code'    => $this->decision->code,
            'meta'    => $this->decision->meta ?: null,
        ], static fn ($v) => $v !== null), $this->status());
    }
}
