<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Services;

use Illuminate\Support\Facades\DB;
use ParticleAcademy\LaravelJobs\Contracts\GatesPublishing;
use ParticleAcademy\LaravelJobs\Enums\JobPostingStatus;
use ParticleAcademy\LaravelJobs\Events\JobPostingClosed;
use ParticleAcademy\LaravelJobs\Events\JobPostingPublished;
use ParticleAcademy\LaravelJobs\Exceptions\PublishNotAllowedException;
use ParticleAcademy\LaravelJobs\Models\JobPosting;

class JobPostingService
{
    public function __construct(private readonly GatesPublishing $gate)
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

        $publishNow = ($attributes['status'] ?? null) === JobPostingStatus::Published->value;

        if ($publishNow) {
            // Creating something already marked published still has to pass the
            // gate — otherwise it is a way around publish().
            $attributes['status'] = JobPostingStatus::Draft->value;
        }

        if (! $publishNow) {
            return JobPosting::query()->create($attributes);
        }

        // Atomic: a gate refusal here rolls the row back rather than leaving an
        // orphan draft behind, which is what callers got before the gate existed.
        return DB::transaction(function () use ($attributes): JobPosting {
            return $this->publish(JobPosting::query()->create($attributes));
        });
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

    /**
     * @throws PublishNotAllowedException
     */
    public function publish(JobPosting $posting): JobPosting
    {
        $decision = $this->gate->check($posting);

        if ($decision->denied()) {
            throw new PublishNotAllowedException($decision);
        }

        $posting->forceFill([
            'status'       => JobPostingStatus::Published,
            'published_at' => $posting->published_at ?? now(),
            'closed_at'    => null,
        ])->save();

        JobPostingPublished::dispatch($posting);

        return $posting->refresh();
    }

    /** Ask whether publishing would be allowed, without attempting it. */
    public function publishDecision(JobPosting $posting)
    {
        return $this->gate->check($posting);
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
}
