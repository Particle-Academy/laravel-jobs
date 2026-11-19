<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Database\Factories;

/**
 * The fake-data source for this package's factories.
 *
 * These factories ship with the package (they are in `autoload`, not
 * `autoload-dev`), so a host calls `JobPosting::factory()` from its own tests.
 * When the host has Faker, Laravel's Factory resolves it into `$this->faker` and
 * we use it unchanged: the host's locale and `$this->faker->seed()` keep working.
 * When it has none, `$this->faker` is null and FakeData stands in. Before this,
 * a host without Faker got a fatal null method call from every factory.
 */
trait UsesFakeData
{
    /** @return \Faker\Generator|FakeData */
    protected function fakeData(): object
    {
        return $this->faker ?? FakeData::instance();
    }
}
