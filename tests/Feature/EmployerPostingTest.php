<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature\EmployerPostingTest;

use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;
use ParticleAcademy\LaravelJobs\Enums\JobPostingStatus;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\AllowAllEmployerAuthorizer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

function employer(string $status = 'approved'): TestEmployer {
    return TestEmployer::query()->create(['name' => 'Acme Security', 'status' => $status]);
}

function allowEmployerActions(): void {
    app()->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());
}

test('employer endpoints are denied by default', function () {
    $employer = employer();

    // No AuthorizesEmployers binding => the package refuses. This is the
    // headline security property: installing it grants nobody anything.
    $this->getJson("/api/jobs/employers/{$employer->id}/postings")->assertForbidden();
    $this->postJson("/api/jobs/employers/{$employer->id}/postings", ['title' => 'X'])->assertForbidden();
});

test('an employer sees its own postings in every status', function () {
    allowEmployerActions();
    $employer = employer();
    $other = employer();

    JobPosting::factory()->forEmployer($employer->id)->create();
    JobPosting::factory()->published()->forEmployer($employer->id)->create();
    JobPosting::factory()->published()->forEmployer($other->id)->create();

    $this->getJson("/api/jobs/employers/{$employer->id}/postings")
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

test('a posting is created as a draft', function () {
    allowEmployerActions();
    $employer = employer();

    $this->postJson("/api/jobs/employers/{$employer->id}/postings", [
        'title'    => 'Overnight patrol guard',
        'location' => 'Torrance, CA',
    ])
        ->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.slug', 'overnight-patrol-guard');
});

test('an unapproved employer may draft but not publish', function () {
    allowEmployerActions();
    $employer = employer(status: 'pending');

    $this->postJson("/api/jobs/employers/{$employer->id}/postings", ['title' => 'Draft is fine'])
        ->assertCreated();

    $posting = JobPosting::query()->sole();

    $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
        ->assertForbidden()
        ->assertJsonPath('message', 'This employer is not approved to publish job postings yet.');

    $this->assertSame(JobPostingStatus::Draft, $posting->refresh()->status);
});

test('an approved employer can publish and close', function () {
    allowEmployerActions();
    $employer = employer();
    $posting = JobPosting::factory()->forEmployer($employer->id)->create();

    $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    $this->getJson('/api/jobs/postings')->assertJsonCount(1, 'data');

    $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    $this->getJson('/api/jobs/postings')->assertJsonCount(0, 'data');
});

test('one employer cannot touch anothers posting', function () {
    allowEmployerActions();
    $mine = employer();
    $theirs = employer();

    $posting = JobPosting::factory()->forEmployer($theirs->id)->create();

    // 404 rather than 403 — the existence of another employer's posting is
    // not something this employer should be able to probe for.
    $this->getJson("/api/jobs/employers/{$mine->id}/postings/{$posting->id}")->assertNotFound();
    $this->patchJson("/api/jobs/employers/{$mine->id}/postings/{$posting->id}", ['title' => 'Hijack'])->assertNotFound();
    $this->postJson("/api/jobs/employers/{$mine->id}/postings/{$posting->id}/publish")->assertNotFound();
});

test('status cannot be changed through a plain update', function () {
    allowEmployerActions();
    $employer = employer();
    $posting = JobPosting::factory()->forEmployer($employer->id)->create();

    $this->patchJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}", [
        'title'  => 'Renamed',
        'status' => 'published',
    ])->assertOk();

    // Publishing has side effects and a gate, so it must not be reachable
    // by smuggling a status field into an ordinary field update.
    $this->assertSame(JobPostingStatus::Draft, $posting->refresh()->status);
    $this->assertSame('Renamed', $posting->title);
});

test('pay range is validated', function () {
    allowEmployerActions();
    $employer = employer();

    $this->postJson("/api/jobs/employers/{$employer->id}/postings", [
        'title'   => 'Bad pay range',
        'pay_min' => 50,
        'pay_max' => 20,
    ])->assertStatus(422)->assertJsonValidationErrors('pay_max');
});
