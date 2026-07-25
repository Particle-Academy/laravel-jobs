<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Services;

use ParticleAcademy\LaravelJobs\Enums\JobPostingStatus;
use ParticleAcademy\LaravelJobs\Events\JobPostingClosed;
use ParticleAcademy\LaravelJobs\Events\JobPostingPublished;
use ParticleAcademy\LaravelJobs\Exceptions\EmployerNotApprovedException;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Support\EmployerGate;

class JobPostingService
{
    public function __construct(private readonly EmployerGate $gate)
    {
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(int|string $employerId, array $attributes): JobPosting
    {
        $attributes['employer_id'] = $employerId;
        $attributes['status'] ??= JobPostingStatus::Draft->value;
        $attributes['currency'] ??= config('laravel-jobs.defaults.currency');

        // Creating something already marked published still has to clear the gate.
        if (($attributes['status'] ?? null) === JobPostingStatus::Published->value) {
            $this->assertMayPublish($employerId);
            $attributes['published_at'] ??= now();
        }

        return JobPosting::query()->create($attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(JobPosting $posting, array $attributes): JobPosting
    {
        // Status transitions go through publish()/close() so their side effects
        // cannot be bypassed by a plain field update.
        unset($attributes['status'], $attributes['employer_id'], $attributes['applications_count']);

        $posting->fill($attributes)->save();

        return $posting->refresh();
    }

    public function publish(JobPosting $posting): JobPosting
    {
        $this->assertMayPublish($posting->employer_id);

        $posting->forceFill([
            'status'       => JobPostingStatus::Published,
            'published_at' => $posting->published_at ?? now(),
            'closed_at'    => null,
        ])->save();

        JobPostingPublished::dispatch($posting);

        return $posting->refresh();
    }

    public function close(JobPosting $posting): JobPosting
    {
        $posting->forceFill([
            'status'    => JobPostingStatus::Closed,
            'closed_at' => now(),
        ])->save();

        JobPostingClosed::dispatch($posting);

        return $posting->refresh();
    }

    public function unpublish(JobPosting $posting): JobPosting
    {
        $posting->forceFill([
            'status'    => JobPostingStatus::Draft,
            'closed_at' => null,
        ])->save();

        return $posting->refresh();
    }

    private function assertMayPublish(int|string $employerId): void
    {
        if (! $this->gate->allowsPublishing($employerId)) {
            throw new EmployerNotApprovedException($this->gate->reason());
        }
    }
}
