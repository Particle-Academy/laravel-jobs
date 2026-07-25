<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Enums;

enum JobPostingStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    /** Is this posting eligible to appear on the public board? */
    public function isPublic(): bool
    {
        return $this === self::Published;
    }

    /** May candidates still apply? */
    public function acceptsApplications(): bool
    {
        return $this === self::Published;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'Draft',
            self::Published => 'Published',
            self::Closed    => 'Closed',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
