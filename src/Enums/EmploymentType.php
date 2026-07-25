<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Enums;

enum EmploymentType: string
{
    case FullTime = 'full_time';
    case PartTime = 'part_time';
    case Contract = 'contract';
    case Temporary = 'temporary';
    case Internship = 'internship';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return match ($this) {
            self::FullTime   => 'Full time',
            self::PartTime   => 'Part time',
            self::Contract   => 'Contract',
            self::Temporary  => 'Temporary',
            self::Internship => 'Internship',
            self::Volunteer  => 'Volunteer',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c) => $c->value, self::cases());
    }
}
