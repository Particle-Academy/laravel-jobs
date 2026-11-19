<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature\AnonymousCandidateTest;

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

function publishedPosting(): JobPosting {
    $employer = TestEmployer::query()->create(['name' => 'Acme Security', 'status' => 'approved']);

    return JobPosting::factory()->published()->forEmployer($employer->id)->create();
}

test('listing your applications anonymously is unauthorized not an error', function () {
    $this->getJson('/api/jobs/my-applications')
        ->assertStatus(401)
        // The message deliberately does NOT name `user_id`. It used to read
        // "Authenticate the request or supply user_id", which told an
        // unauthenticated caller how to spoof a candidate -- see
        // CandidateIdentitySpoofTest.
        ->assertJsonPath('message', 'Unable to resolve the candidate for this request.');
});

test('applying anonymously is unauthorized not an error', function () {
    $posting = publishedPosting();

    $this->postJson("/api/jobs/postings/{$posting->slug}/applications")
        ->assertStatus(401);
});

test('withdrawing anonymously is unauthorized not an error', function () {
    $application = JobApplication::factory()->create([
        'job_posting_id' => publishedPosting()->id,
    ]);

    $this->postJson("/api/jobs/applications/{$application->id}/withdraw")
        ->assertStatus(401);
});

test('the status applies to a plain browser request too', function () {
    // Not getJson(). A host that mounts these on `web` gets browser-shaped
    // requests, and render() returns null for those — so without
    // HttpExceptionInterface the status was lost and Laravel produced a 500.
    $this->get('/api/jobs/my-applications')->assertStatus(401);
});

test('the public board stays open to anonymous requests', function () {
    // The fix must not have closed the parts that are meant to be public.
    publishedPosting();

    $this->getJson('/api/jobs/postings')->assertOk()->assertJsonCount(1, 'data');
});
