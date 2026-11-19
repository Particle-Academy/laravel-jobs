<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature\JobBoardTest;

use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

function employer(string $status = 'approved'): TestEmployer {
    return TestEmployer::query()->create(['name' => 'Acme Security', 'status' => $status]);
}

test('the board lists only published postings', function () {
    $employer = employer();

    JobPosting::factory()->published()->forEmployer($employer->id)->create(['title' => 'Visible role']);
    JobPosting::factory()->forEmployer($employer->id)->create(['title' => 'Draft role']);
    JobPosting::factory()->closed()->forEmployer($employer->id)->create(['title' => 'Closed role']);

    $response = $this->getJson('/api/jobs/postings')->assertOk();

    $titles = array_column($response->json('data'), 'title');

    $this->assertSame(['Visible role'], $titles);
});

test('expired postings drop off the board', function () {
    $employer = employer();
    JobPosting::factory()->expired()->forEmployer($employer->id)->create();

    $this->getJson('/api/jobs/postings')->assertOk()->assertJsonCount(0, 'data');
});

test('a draft posting is not reachable by slug', function () {
    $employer = employer();
    $posting = JobPosting::factory()->forEmployer($employer->id)->create();

    $this->getJson("/api/jobs/postings/{$posting->slug}")->assertNotFound();
});

test('the board can be searched and filtered', function () {
    $employer = employer();

    JobPosting::factory()->published()->forEmployer($employer->id)->create([
        'title' => 'Overnight patrol guard', 'location' => 'Torrance, CA', 'is_remote' => false,
    ]);
    JobPosting::factory()->published()->forEmployer($employer->id)->create([
        'title' => 'Remote dispatcher', 'location' => 'Anywhere', 'is_remote' => true,
    ]);

    $this->getJson('/api/jobs/postings?search=patrol')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/jobs/postings?is_remote=1')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/jobs/postings?location=Torrance')->assertOk()->assertJsonCount(1, 'data');
});

test('slugs are generated and disambiguated', function () {
    $employer = employer();

    $a = JobPosting::query()->create(['employer_id' => $employer->id, 'title' => 'Security Guard']);
    $b = JobPosting::query()->create(['employer_id' => $employer->id, 'title' => 'Security Guard']);

    $this->assertSame('security-guard', $a->slug);
    $this->assertSame('security-guard-2', $b->slug);
});
