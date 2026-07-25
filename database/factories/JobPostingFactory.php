<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelJobs\Enums\EmploymentType;
use ParticleAcademy\LaravelJobs\Enums\JobPostingStatus;
use ParticleAcademy\LaravelJobs\Models\JobPosting;

/**
 * @extends Factory<JobPosting>
 */
class JobPostingFactory extends Factory
{
    protected $model = JobPosting::class;

    public function definition(): array
    {
        $title = $this->faker->jobTitle();

        return [
            'employer_id'     => 1,
            'title'           => $title,
            'slug'            => JobPosting::generateSlug($title),
            'description'     => $this->faker->paragraph(),
            'requirements'    => $this->faker->sentence(),
            'employment_type' => $this->faker->randomElement(EmploymentType::cases())->value,
            'location'        => $this->faker->city().', '.$this->faker->stateAbbr(),
            'is_remote'       => false,
            'pay_min'         => 20,
            'pay_max'         => 30,
            'pay_unit'        => 'hour',
            'currency'        => 'USD',
            'status'          => JobPostingStatus::Draft->value,
            'openings'        => 1,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status'       => JobPostingStatus::Published->value,
            'published_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status'    => JobPostingStatus::Closed->value,
            'closed_at' => now(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status'       => JobPostingStatus::Published->value,
            'published_at' => now()->subMonth(),
            'expires_at'   => now()->subDay(),
        ]);
    }

    public function forEmployer(int|string $employerId): static
    {
        return $this->state(fn () => ['employer_id' => $employerId]);
    }
}
