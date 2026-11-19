<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature\PublishGateTest;

use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;
use ParticleAcademy\LaravelJobs\Contracts\GatesPublishing;
use ParticleAcademy\LaravelJobs\Enums\JobPostingStatus;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Support\PublishDecision;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\AllowAllEmployerAuthorizer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

/**
 * The publish gate is the seam hosts use to add their own rule — paid listings,
 * plan quotas, manual review. Drafting is never gated.
 */

function employer(string $status = 'approved'): TestEmployer {
    return TestEmployer::query()->create(['name' => 'Acme Security', 'status' => $status]);
}

function allowEmployerActions(): void {
    app()->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());
}

/** A host gate that always wants money. */
function bindPayingGate(): void {
    app()->instance(GatesPublishing::class, new class implements GatesPublishing
    {
        public function check(JobPosting $posting): PublishDecision
        {
            return PublishDecision::deny(
                reason: 'Publishing this listing costs $49.',
                code: 'payment_required',
                meta: ['checkout_url' => 'https://checkout.test/session/abc'],
            );
        }
    });
}

test('a host gate can demand payment and say where to pay', function () {
    allowEmployerActions();
    bindPayingGate();

    $employer = employer();
    $posting = JobPosting::factory()->forEmployer($employer->id)->create();

    $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
        // 402 rather than 403: the only thing missing is money.
        ->assertStatus(402)
        ->assertJsonPath('code', 'payment_required')
        ->assertJsonPath('meta.checkout_url', 'https://checkout.test/session/abc');

    $this->assertSame(JobPostingStatus::Draft, $posting->refresh()->status);
});

test('drafting is never gated', function () {
    allowEmployerActions();
    bindPayingGate();

    $employer = employer();

    // Even with a gate that refuses everything, an employer can still write
    // the posting — they are only stopped at the point of going live.
    $this->postJson("/api/jobs/employers/{$employer->id}/postings", ['title' => 'Draft is free'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft');
});

test('creating as published cannot skip the gate', function () {
    allowEmployerActions();
    bindPayingGate();

    $employer = employer();

    $service = $this->app->make(\ParticleAcademy\LaravelJobs\Services\JobPostingService::class);

    try {
        $service->create($employer->id, ['title' => 'Sneaky', 'status' => 'published']);
        $this->fail('Creating a published posting should have hit the gate.');
    } catch (\ParticleAcademy\LaravelJobs\Exceptions\PublishNotAllowedException $e) {
        $this->assertSame('payment_required', $e->decision->code);
    }

    // And it rolls back rather than leaving an orphan draft behind.
    $this->assertSame(0, JobPosting::query()->count());
});

test('a host gate can allow what the approval column would refuse', function () {
    allowEmployerActions();

    $this->app->instance(GatesPublishing::class, new class implements GatesPublishing
    {
        public function check(JobPosting $posting): PublishDecision
        {
            return PublishDecision::allow();
        }
    });

    // Employer is NOT approved, but the host's rule is authoritative.
    $employer = employer(status: 'pending');
    $posting = JobPosting::factory()->forEmployer($employer->id)->create();

    $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');
});

test('the decision can be asked for without publishing', function () {
    bindPayingGate();

    $posting = JobPosting::factory()->forEmployer(employer()->id)->create();

    $decision = $this->app
        ->make(\ParticleAcademy\LaravelJobs\Services\JobPostingService::class)
        ->publishDecision($posting);

    // Lets a host render "Publish — $49" without attempting the transition.
    $this->assertTrue($decision->denied());
    $this->assertSame('payment_required', $decision->code);
    $this->assertSame(JobPostingStatus::Draft, $posting->refresh()->status);
});

test('the default gate still enforces employer approval', function () {
    allowEmployerActions();

    $employer = employer(status: 'pending');
    $posting = JobPosting::factory()->forEmployer($employer->id)->create();

    $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
        ->assertForbidden()
        ->assertJsonPath('code', 'employer_not_approved');
});
