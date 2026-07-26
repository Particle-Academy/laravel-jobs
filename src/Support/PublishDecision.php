<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Support;

/**
 * The answer to "may this posting go live?".
 *
 * Deliberately richer than a boolean: a host that charges per listing needs to
 * say *why* it refused and *where* the employer should go next, and a caller
 * that only got `false` would have to guess.
 */
final class PublishDecision
{
    /**
     * @param  array<string, mixed>  $meta
     */
    private function __construct(
        public readonly bool $allowed,
        public readonly ?string $reason = null,
        public readonly ?string $code = null,
        public readonly array $meta = [],
    ) {
    }

    public static function allow(): self
    {
        return new self(allowed: true);
    }

    /**
     * @param  string|null  $code   Machine-readable, e.g. "payment_required".
     * @param  array<string, mixed>  $meta  Anything the host needs to act on it,
     *                                      e.g. ['checkout_url' => '…'].
     */
    public static function deny(string $reason, ?string $code = null, array $meta = []): self
    {
        return new self(allowed: false, reason: $reason, code: $code, meta: $meta);
    }

    public function denied(): bool
    {
        return ! $this->allowed;
    }
}
