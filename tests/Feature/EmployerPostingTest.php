<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature;

use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;
use ParticleAcademy\LaravelJobs\Enums\JobPostingStatus;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\AllowAllEmployerAuthorizer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

class EmployerPostingTest extends TestCase
{
    private function employer(string $status = 'approved'): TestEmployer
    {
        return TestEmployer::query()->create(['name' => 'Acme Security', 'status' => $status]);
    }

    private function allowEmployerActions(): void
    {
        $this->app->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());
    }

    public function test_employer_endpoints_are_denied_by_default(): void
    {
        $employer = $this->employer();

        // No AuthorizesEmployers binding => the package refuses. This is the
        // headline security property: installing it grants nobody anything.
        $this->getJson("/api/jobs/employers/{$employer->id}/postings")->assertForbidden();
        $this->postJson("/api/jobs/employers/{$employer->id}/postings", ['title' => 'X'])->assertForbidden();
    }

    public function test_an_employer_sees_its_own_postings_in_every_status(): void
    {
        $this->allowEmployerActions();
        $employer = $this->employer();
        $other = $this->employer();

        JobPosting::factory()->forEmployer($employer->id)->create();
        JobPosting::factory()->published()->forEmployer($employer->id)->create();
        JobPosting::factory()->published()->forEmployer($other->id)->create();

        $this->getJson("/api/jobs/employers/{$employer->id}/postings")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_a_posting_is_created_as_a_draft(): void
    {
        $this->allowEmployerActions();
        $employer = $this->employer();

        $this->postJson("/api/jobs/employers/{$employer->id}/postings", [
            'title'    => 'Overnight patrol guard',
            'location' => 'Torrance, CA',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.slug', 'overnight-patrol-guard');
    }

    public function test_an_unapproved_employer_may_draft_but_not_publish(): void
    {
        $this->allowEmployerActions();
        $employer = $this->employer(status: 'pending');

        $this->postJson("/api/jobs/employers/{$employer->id}/postings", ['title' => 'Draft is fine'])
            ->assertCreated();

        $posting = JobPosting::query()->sole();

        $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
            ->assertForbidden()
            ->assertJsonPath('message', 'This employer is not approved to publish job postings yet.');

        $this->assertSame(JobPostingStatus::Draft, $posting->refresh()->status);
    }

    public function test_an_approved_employer_can_publish_and_close(): void
    {
        $this->allowEmployerActions();
        $employer = $this->employer();
        $posting = JobPosting::factory()->forEmployer($employer->id)->create();

        $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->getJson('/api/jobs/postings')->assertJsonCount(1, 'data');

        $this->postJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}/close")
            ->assertOk()
            ->assertJsonPath('data.status', 'closed');

        $this->getJson('/api/jobs/postings')->assertJsonCount(0, 'data');
    }

    public function test_one_employer_cannot_touch_anothers_posting(): void
    {
        $this->allowEmployerActions();
        $mine = $this->employer();
        $theirs = $this->employer();

        $posting = JobPosting::factory()->forEmployer($theirs->id)->create();

        // 404 rather than 403 — the existence of another employer's posting is
        // not something this employer should be able to probe for.
        $this->getJson("/api/jobs/employers/{$mine->id}/postings/{$posting->id}")->assertNotFound();
        $this->patchJson("/api/jobs/employers/{$mine->id}/postings/{$posting->id}", ['title' => 'Hijack'])->assertNotFound();
        $this->postJson("/api/jobs/employers/{$mine->id}/postings/{$posting->id}/publish")->assertNotFound();
    }

    public function test_status_cannot_be_changed_through_a_plain_update(): void
    {
        $this->allowEmployerActions();
        $employer = $this->employer();
        $posting = JobPosting::factory()->forEmployer($employer->id)->create();

        $this->patchJson("/api/jobs/employers/{$employer->id}/postings/{$posting->id}", [
            'title'  => 'Renamed',
            'status' => 'published',
        ])->assertOk();

        // Publishing has side effects and a gate, so it must not be reachable
        // by smuggling a status field into an ordinary field update.
        $this->assertSame(JobPostingStatus::Draft, $posting->refresh()->status);
        $this->assertSame('Renamed', $posting->title);
    }

    public function test_pay_range_is_validated(): void
    {
        $this->allowEmployerActions();
        $employer = $this->employer();

        $this->postJson("/api/jobs/employers/{$employer->id}/postings", [
            'title'   => 'Bad pay range',
            'pay_min' => 50,
            'pay_max' => 20,
        ])->assertStatus(422)->assertJsonValidationErrors('pay_max');
    }
}
