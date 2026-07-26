<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Contracts;

use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Support\PublishDecision;

/**
 * Decides whether a posting may go live.
 *
 * Drafting is never gated — this is only consulted when publishing. The default
 * binding applies the `employer_gate` approval column from config, which is what
 * this package did before the gate was extractable.
 *
 * Hosts with a richer rule (paid listings, plan quotas, manual review) bind
 * their own. Returning a denial with a `code` and `meta` lets the host's own UI
 * react — sending the employer to checkout, say — instead of just showing an
 * error.
 *
 *   $this->app->bind(GatesPublishing::class, PaidListingGate::class);
 */
interface GatesPublishing
{
    public function check(JobPosting $posting): PublishDecision;
}
