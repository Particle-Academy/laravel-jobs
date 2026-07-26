<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature;

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
class PublishGateTest extends TestCase
{
    private function employer(string $status = 'approved'): TestEmployer
    {
        return TestEmployer::query()->create(['name' => 'Acme Security', 'status' => $status]);
    }

    private function allowEmployerActions(): void
    {
        $this->app->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());
    }

    /** A host gate that always wants money. */
    private function bindPayingGate(): void
    {
        $this->app->instance(GatesPublishing::class, new class implements GatesPublishing
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

    public function test_a_host_gate_can_demand_payment_and_say_where_to_pay(): void
    {
        $this->allowEmployerActions();
        $this->bindPayingGate();

        $employer = $this->employer();
        $posting = JobPosting::factory()->forEmployer($employer->id)->create();

        $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
            // 402 rather than 403: the only thing missing is money.
            ->assertStatus(402)
            ->assertJsonPath('code', 'payment_required')
            ->assertJsonPath('meta.checkout_url', 'https://checkout.test/session/abc');

        $this->assertSame(JobPostingStatus::Draft, $posting->refresh()->status);
    }

    public function test_drafting_is_never_gated(): void
    {
        $this->allowEmployerActions();
        $this->bindPayingGate();

        $employer = $this->employer();

        // Even with a gate that refuses everything, an employer can still write
        // the posting — they are only stopped at the point of going live.
        $this->postJson("/api/jobs/employers/{$employer->id}/postings", ['title' => 'Draft is free'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_creating_as_published_cannot_skip_the_gate(): void
    {
        $this->allowEmployerActions();
        $this->bindPayingGate();

        $employer = $this->employer();

        $service = $this->app->make(\ParticleAcademy\LaravelJobs\Services\JobPostingService::class);

        try {
            $service->create($employer->id, ['title' => 'Sneaky', 'status' => 'published']);
            $this->fail('Creating a published posting should have hit the gate.');
        } catch (\ParticleAcademy\LaravelJobs\Exceptions\PublishNotAllowedException $e) {
            $this->assertSame('payment_required', $e->decision->code);
        }

        // And it rolls back rather than leaving an orphan draft behind.
        $this->assertSame(0, JobPosting::query()->count());
    }

    public function test_a_host_gate_can_allow_what_the_approval_column_would_refuse(): void
    {
        $this->allowEmployerActions();

        $this->app->instance(GatesPublishing::class, new class implements GatesPublishing
        {
            public function check(JobPosting $posting): PublishDecision
            {
                return PublishDecision::allow();
            }
        });

        // Employer is NOT approved, but the host's rule is authoritative.
        $employer = $this->employer(status: 'pending');
        $posting = JobPosting::factory()->forEmployer($employer->id)->create();

        $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');
    }

    public function test_the_decision_can_be_asked_for_without_publishing(): void
    {
        $this->bindPayingGate();

        $posting = JobPosting::factory()->forEmployer($this->employer()->id)->create();

        $decision = $this->app
            ->make(\ParticleAcademy\LaravelJobs\Services\JobPostingService::class)
            ->publishDecision($posting);

        // Lets a host render "Publish — $49" without attempting the transition.
        $this->assertTrue($decision->denied());
        $this->assertSame('payment_required', $decision->code);
        $this->assertSame(JobPostingStatus::Draft, $posting->refresh()->status);
    }

    public function test_the_default_gate_still_enforces_employer_approval(): void
    {
        $this->allowEmployerActions();

        $employer = $this->employer(status: 'pending');
        $posting = JobPosting::factory()->forEmployer($employer->id)->create();

        $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
            ->assertForbidden()
            ->assertJsonPath('code', 'employer_not_approved');
    }
}
