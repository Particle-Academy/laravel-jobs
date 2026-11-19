<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature\ApplicationTest;

use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;
use ParticleAcademy\LaravelJobs\Enums\ApplicationStatus;
use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\AllowAllEmployerAuthorizer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestUser;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

function employer(): TestEmployer {
    return TestEmployer::query()->create(['name' => 'Acme Security', 'status' => 'approved']);
}

function candidate(string $email = 'guard@example.test'): TestUser {
    return TestUser::query()->create(['name' => 'Sam Guard', 'email' => $email]);
}

function publishedPosting(): JobPosting {
    return JobPosting::factory()->published()->forEmployer(employer()->id)->create();
}

test('a candidate can apply to a published posting', function () {
    $posting = publishedPosting();
    $user = candidate();

    $this->actingAs($user)
        ->postJson("/api/jobs/postings/{$posting->slug}/applications", [
            'cover_letter' => 'I have five years on night patrol.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'submitted');

    $this->assertSame(1, $posting->refresh()->applications_count);
});

test('applying twice is rejected', function () {
    $posting = publishedPosting();
    $user = candidate();

    $this->actingAs($user)->postJson("/api/jobs/postings/{$posting->slug}/applications")->assertCreated();
    $this->actingAs($user)->postJson("/api/jobs/postings/{$posting->slug}/applications")->assertStatus(409);

    $this->assertSame(1, $posting->refresh()->applications_count);
});

test('a draft posting cannot be applied to', function () {
    $posting = JobPosting::factory()->forEmployer(employer()->id)->create();

    $this->actingAs(candidate())
        ->postJson("/api/jobs/postings/{$posting->slug}/applications")
        ->assertNotFound();
});

test('a candidate sees only their own applications', function () {
    $posting = publishedPosting();
    $mine = candidate('mine@example.test');
    $theirs = candidate('theirs@example.test');

    JobApplication::factory()->forCandidate($mine->id)->create(['job_posting_id' => $posting->id]);
    JobApplication::factory()->forCandidate($theirs->id)->create(['job_posting_id' => $posting->id]);

    $this->actingAs($mine)->getJson('/api/jobs/my-applications')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('a candidate cannot withdraw someone elses application', function () {
    $posting = publishedPosting();
    $mine = candidate('mine@example.test');
    $theirs = candidate('theirs@example.test');

    $application = JobApplication::factory()->forCandidate($theirs->id)->create([
        'job_posting_id' => $posting->id,
    ]);

    $this->actingAs($mine)
        ->postJson("/api/jobs/applications/{$application->id}/withdraw")
        ->assertNotFound();

    $this->assertSame(ApplicationStatus::Submitted, $application->refresh()->status);
});

test('an employer reviews and advances applicants', function () {
    $this->app->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());

    $employer = employer();
    $posting = JobPosting::factory()->published()->forEmployer($employer->id)->create();
    $user = candidate();

    $application = JobApplication::factory()->forCandidate($user->id)->create([
        'job_posting_id' => $posting->id,
    ]);

    $this->getJson("/api/jobs/employers/{$employer->id}/applications")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.candidate.email', 'guard@example.test');

    $this->patchJson("/api/jobs/employers/{$employer->id}/applications/{$application->id}", [
        'status'         => 'shortlisted',
        'employer_notes' => 'Strong references.',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'shortlisted');
});

test('an employer cannot set the candidate only withdrawn status', function () {
    $this->app->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());

    $employer = employer();
    $posting = JobPosting::factory()->published()->forEmployer($employer->id)->create();
    $application = JobApplication::factory()->create(['job_posting_id' => $posting->id]);

    $this->patchJson("/api/jobs/employers/{$employer->id}/applications/{$application->id}", [
        'status' => 'withdrawn',
    ])->assertStatus(422)->assertJsonValidationErrors('status');
});

test('an employer cannot see applications for another employer', function () {
    $this->app->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());

    $mine = employer();
    $theirs = employer();

    $posting = JobPosting::factory()->published()->forEmployer($theirs->id)->create();
    $application = JobApplication::factory()->create(['job_posting_id' => $posting->id]);

    $this->getJson("/api/jobs/employers/{$mine->id}/applications")
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $this->patchJson("/api/jobs/employers/{$mine->id}/applications/{$application->id}", [
        'status' => 'hired',
    ])->assertNotFound();
});
