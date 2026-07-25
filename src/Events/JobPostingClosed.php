<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use ParticleAcademy\LaravelJobs\Models\JobPosting;

class JobPostingClosed
{
    use Dispatchable, SerializesModels;

    public function __construct(public JobPosting $posting)
    {
    }
}
