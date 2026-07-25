<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Enums;

enum ApplicationStatus: string
{
    case Submitted = 'submitted';
    case Reviewing = 'reviewing';
    case Shortlisted = 'shortlisted';
    case Rejected = 'rejected';
    case Hired = 'hired';
    case Withdrawn = 'withdrawn';

    /** Has the employer finished with this application? */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Hired, self::Withdrawn], true);
    }

    /** Statuses an employer may set. Withdrawn belongs to the candidate. */
    public static function employerAssignable(): array
    {
        return [self::Submitted, self::Reviewing, self::Shortlisted, self::Rejected, self::Hired];
    }

    public function label(): string
    {
        return match ($this) {
            self::Submitted   => 'Submitted',
            self::Reviewing   => 'Reviewing',
            self::Shortlisted => 'Shortlisted',
            self::Rejected    => 'Not selected',
            self::Hired       => 'Hired',
            self::Withdrawn   => 'Withdrawn',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
