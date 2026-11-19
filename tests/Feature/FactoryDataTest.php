<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature\FactoryDataTest;

use ParticleAcademy\LaravelJobs\Database\Factories\FakeData;
use ParticleAcademy\LaravelJobs\Database\Factories\JobPostingFactory;
use ParticleAcademy\LaravelJobs\Enums\EmploymentType;
use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Models\JobPosting;

/*
| The shipped factories carry no third-party fake-data dependency. They use the
| host's Faker when it has one and FakeData otherwise.
*/

test('the suite runs without a fake data library installed', function () {
    // If Faker crept back in transitively, the fallback path would stop being
    // the one under test.
    expect(class_exists(\Faker\Generator::class))->toBeFalse();
});

test('the shipped factories create rows with no Faker present', function () {
    $application = JobApplication::factory()->create();
    $posting = JobPosting::find($application->job_posting_id);

    expect($posting->title)->toBeString()->not->toBe('')
        ->and($posting->location)->toMatch('/^[A-Za-z ]+, [A-Z]{2}$/')
        ->and(EmploymentType::tryFrom($posting->employment_type instanceof EmploymentType ? $posting->employment_type->value : (string) $posting->employment_type))->not->toBeNull()
        ->and($application->contact_email)->toEndWith('@example.com')
        ->and($application->contact_phone)->toMatch('/^[(]\d{3}[)] 555-01\d{2}$/');
});

test('the same seed gives the same data, so fixtures are deterministic', function () {
    $draw = fn () => [FakeData::instance()->jobTitle(), FakeData::instance()->paragraph(), FakeData::instance()->phoneNumber()];

    FakeData::reset(42);
    $first = $draw();
    FakeData::reset(42);
    $second = $draw();
    FakeData::reset(43);
    $other = $draw();

    expect($second)->toBe($first)->and($other)->not->toBe($first);
});

test('safeEmail never repeats', function () {
    $emails = [];
    for ($i = 0; $i < 200; $i++) {
        $emails[] = FakeData::instance()->safeEmail();
    }

    expect(array_unique($emails))->toHaveCount(200);
});

test('a host Faker is used in preference to FakeData', function () {
    // A host with Faker installed: Laravel's Factory resolves it into $this->faker.
    $hostFaker = new class {
        public function jobTitle(): string { return 'Host Faker Role'; }
        public function paragraph(int $n = 3): string { return 'host paragraph'; }
        public function sentence(int $n = 6): string { return 'host sentence'; }
        public function randomElement(array $a): mixed { return $a[0]; }
        public function city(): string { return 'Hostville'; }
        public function stateAbbr(): string { return 'HV'; }
    };

    $factory = new class ($hostFaker) extends JobPostingFactory {
        public function __construct(private object $host) { parent::__construct(); }
        protected function withFaker() { return $this->host; }
    };

    $row = $factory->definition();

    expect($row['title'])->toBe('Host Faker Role')
        ->and($row['description'])->toBe('host paragraph')
        ->and($row['requirements'])->toBe('host sentence')
        ->and($row['location'])->toBe('Hostville, HV')
        ->and($row['employment_type'])->toBe(EmploymentType::cases()[0]->value);
});
