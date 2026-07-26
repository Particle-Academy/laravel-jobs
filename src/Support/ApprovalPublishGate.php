<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Support;

use ParticleAcademy\LaravelJobs\Contracts\GatesPublishing;
use ParticleAcademy\LaravelJobs\Models\JobPosting;

/**
 * The default publish rule: the employer must be approved.
 *
 * This is exactly what the package enforced before the gate became bindable, so
 * hosts that never bind their own see no change in behaviour.
 */
class ApprovalPublishGate implements GatesPublishing
{
    public function __construct(private readonly EmployerGate $employers)
    {
    }

    public function check(JobPosting $posting): PublishDecision
    {
        if ($this->employers->allowsPublishing($posting->employer_id)) {
            return PublishDecision::allow();
        }

        return PublishDecision::deny(
            reason: $this->employers->reason(),
            code: 'employer_not_approved',
        );
    }
}
