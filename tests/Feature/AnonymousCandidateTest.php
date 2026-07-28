<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature;

use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

/**
 * The candidate endpoints are reachable unauthenticated — the package mounts
 * one middleware group and cannot assume the host put `auth` on it. An
 * anonymous request must therefore answer 401, not fall over.
 *
 * Found by dogfooding: a route smoke test in a host app caught
 * GET /api/jobs/my-applications returning 500 to a guest.
 */
class AnonymousCandidateTest extends TestCase
{
    private function publishedPosting(): JobPosting
    {
        $employer = TestEmployer::query()->create(['name' => 'Acme Security', 'status' => 'approved']);

        return JobPosting::factory()->published()->forEmployer($employer->id)->create();
    }

    public function test_listing_your_applications_anonymously_is_unauthorized_not_an_error(): void
    {
        $this->getJson('/api/jobs/my-applications')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unable to resolve candidate. Authenticate the request or supply user_id.');
    }

    public function test_applying_anonymously_is_unauthorized_not_an_error(): void
    {
        $posting = $this->publishedPosting();

        $this->postJson("/api/jobs/postings/{$posting->slug}/applications")
            ->assertStatus(401);
    }

    public function test_withdrawing_anonymously_is_unauthorized_not_an_error(): void
    {
        $application = JobApplication::factory()->create([
            'job_posting_id' => $this->publishedPosting()->id,
        ]);

        $this->postJson("/api/jobs/applications/{$application->id}/withdraw")
            ->assertStatus(401);
    }

    public function test_the_status_applies_to_a_plain_browser_request_too(): void
    {
        // Not getJson(). A host that mounts these on `web` gets browser-shaped
        // requests, and render() returns null for those — so without
        // HttpExceptionInterface the status was lost and Laravel produced a 500.
        $this->get('/api/jobs/my-applications')->assertStatus(401);
    }

    public function test_the_public_board_stays_open_to_anonymous_requests(): void
    {
        // The fix must not have closed the parts that are meant to be public.
        $this->publishedPosting();

        $this->getJson('/api/jobs/postings')->assertOk()->assertJsonCount(1, 'data');
    }
}
