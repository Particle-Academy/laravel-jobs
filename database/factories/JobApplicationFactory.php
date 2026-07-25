<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use ParticleAcademy\LaravelJobs\Enums\ApplicationStatus;
use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Models\JobPosting;

/**
 * @extends Factory<JobApplication>
 */
class JobApplicationFactory extends Factory
{
    protected $model = JobApplication::class;

    public function definition(): array
    {
        return [
            'job_posting_id' => JobPosting::factory()->published(),
            'user_id'        => 1,
            'cover_letter'   => $this->faker->paragraph(),
            'contact_email'  => $this->faker->safeEmail(),
            'contact_phone'  => $this->faker->phoneNumber(),
            'status'         => ApplicationStatus::Submitted->value,
            'submitted_at'   => now(),
        ];
    }

    public function status(ApplicationStatus $status): static
    {
        return $this->state(fn () => [
            'status'            => $status->value,
            'status_changed_at' => now(),
        ]);
    }

    public function forCandidate(int|string $userId): static
    {
        return $this->state(fn () => ['user_id' => $userId]);
    }
}
