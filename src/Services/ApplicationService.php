<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Services;

use Illuminate\Support\Facades\DB;
use ParticleAcademy\LaravelJobs\Enums\ApplicationStatus;
use ParticleAcademy\LaravelJobs\Events\ApplicationStatusChanged;
use ParticleAcademy\LaravelJobs\Events\ApplicationSubmitted;
use ParticleAcademy\LaravelJobs\Exceptions\ApplicationsClosedException;
use ParticleAcademy\LaravelJobs\Exceptions\DuplicateApplicationException;
use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Models\JobPosting;

class ApplicationService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function submit(JobPosting $posting, int|string $userId, array $attributes = []): JobApplication
    {
        if (! $posting->acceptsApplications()) {
            throw new ApplicationsClosedException(
                'This posting is not accepting applications.',
            );
        }

        if (! (bool) config('laravel-jobs.defaults.allow_duplicate_applications', false)) {
            $exists = JobApplication::query()
                ->where('job_posting_id', $posting->id)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                throw new DuplicateApplicationException(
                    'You have already applied to this posting.',
                );
            }
        }

        return DB::transaction(function () use ($posting, $userId, $attributes): JobApplication {
            $application = JobApplication::query()->create([
                ...$attributes,
                'job_posting_id' => $posting->id,
                'user_id'        => $userId,
                'status'         => ApplicationStatus::Submitted->value,
                'submitted_at'   => now(),
            ]);

            // Denormalised counter — the board and portal both show it on every row.
            $posting->increment('applications_count');

            ApplicationSubmitted::dispatch($application);

            return $application;
        });
    }

    public function changeStatus(JobApplication $application, ApplicationStatus $status, ?string $notes = null): JobApplication
    {
        $previous = $application->status;

        $application->forceFill([
            'status'            => $status,
            'status_changed_at' => now(),
            ...($notes !== null ? ['employer_notes' => $notes] : []),
        ])->save();

        ApplicationStatusChanged::dispatch($application, $previous);

        return $application->refresh();
    }

    public function withdraw(JobApplication $application): JobApplication
    {
        return $this->changeStatus($application, ApplicationStatus::Withdrawn);
    }
}
