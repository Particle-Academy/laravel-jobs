<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use ParticleAcademy\LaravelJobs\Enums\ApplicationStatus;
use ParticleAcademy\LaravelJobs\Models\JobApplication;

class ApplicationStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public JobApplication $application,
        public ApplicationStatus $previous,
    ) {
    }
}
