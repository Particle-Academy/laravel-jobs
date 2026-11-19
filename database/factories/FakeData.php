<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Database\Factories;

use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * A first-party, deterministic stand-in for the handful of Faker methods this
 * package's factories call. It exists so the package needs no third-party fake
 * data library: fakerphp/faker fails the suite's freshness bar.
 *
 * Factories use the host's Faker when the host has one (see UsesFakeData), so a
 * consumer's seeding and locale still apply. This class is the fallback, and the
 * only source this package's own suite uses.
 *
 * Seeded: the same seed yields the same sequence. Call reset() to restart it.
 * Only the methods the factories actually call are provided.
 */
final class FakeData
{
    public const DEFAULT_SEED = 20261118;

    private const WORDS = [
        'alpha', 'amber', 'anchor', 'arc', 'atlas', 'beacon', 'birch', 'bloom',
        'bridge', 'cedar', 'circuit', 'cobalt', 'comet', 'coral', 'delta', 'drift',
        'ember', 'falcon', 'fern', 'field', 'flint', 'forge', 'garnet', 'glade',
        'harbor', 'helix', 'iris', 'jade', 'kernel', 'lantern', 'lumen', 'maple',
    ];

    private const JOB_TITLES = [
        'Software Engineer', 'Data Analyst', 'Product Designer', 'Nurse',
        'Electrician', 'Accountant', 'Line Cook', 'Warehouse Associate',
        'Customer Support Specialist', 'Project Manager', 'Teacher', 'Welder',
    ];

    /** [city, state abbreviation] pairs, kept together so a location is real. */
    private const PLACES = [
        ['Austin', 'TX'], ['Denver', 'CO'], ['Portland', 'OR'], ['Columbus', 'OH'],
        ['Raleigh', 'NC'], ['Madison', 'WI'], ['Tucson', 'AZ'], ['Albany', 'NY'],
    ];

    private static ?self $instance = null;

    private Randomizer $random;

    private int $emails = 0;

    private function __construct(int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(self::DEFAULT_SEED);
    }

    /** Restart the sequence from the given seed. */
    public static function reset(int $seed = self::DEFAULT_SEED): void
    {
        self::$instance = new self($seed);
    }

    /**
     * @template T
     * @param array<array-key, T> $items
     * @return T
     */
    public function randomElement(array $items): mixed
    {
        $values = array_values($items);

        return $values[$this->random->getInt(0, count($values) - 1)];
    }

    public function sentence(int $words = 6): string
    {
        $out = [];
        for ($i = 0; $i < max(1, $words); $i++) {
            $out[] = $this->randomElement(self::WORDS);
        }

        return ucfirst(implode(' ', $out)) . '.';
    }

    public function paragraph(int $sentences = 3): string
    {
        $out = [];
        for ($i = 0; $i < max(1, $sentences); $i++) {
            $out[] = $this->sentence($this->random->getInt(4, 8));
        }

        return implode(' ', $out);
    }

    public function jobTitle(): string
    {
        return $this->randomElement(self::JOB_TITLES);
    }

    public function city(): string
    {
        return $this->randomElement(self::PLACES)[0];
    }

    public function stateAbbr(): string
    {
        return $this->randomElement(self::PLACES)[1];
    }

    /** Always on the reserved example.com domain (RFC 2606), and never repeated. */
    public function safeEmail(): string
    {
        $this->emails++;

        return $this->randomElement(self::WORDS) . '.' . $this->emails . '@example.com';
    }

    /** In the 555-01xx range, which is reserved for fiction. */
    public function phoneNumber(): string
    {
        return sprintf('(%03d) 555-01%02d', $this->random->getInt(200, 999), $this->random->getInt(0, 99));
    }
}
