<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature;

use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;
use ParticleAcademy\LaravelJobs\Enums\ApplicationStatus;
use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\AllowAllEmployerAuthorizer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestUser;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

class ApplicationTest extends TestCase
{
    private function employer(): TestEmployer
    {
        return TestEmployer::query()->create(['name' => 'Acme Security', 'status' => 'approved']);
    }

    private function candidate(string $email = 'guard@example.test'): TestUser
    {
        return TestUser::query()->create(['name' => 'Sam Guard', 'email' => $email]);
    }

    private function publishedPosting(): JobPosting
    {
        return JobPosting::factory()->published()->forEmployer($this->employer()->id)->create();
    }

    public function test_a_candidate_can_apply_to_a_published_posting(): void
    {
        $posting = $this->publishedPosting();
        $user = $this->candidate();

        $this->actingAs($user)
            ->postJson("/api/jobs/postings/{$posting->slug}/applications", [
                'cover_letter' => 'I have five years on night patrol.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted');

        $this->assertSame(1, $posting->refresh()->applications_count);
    }

    public function test_applying_twice_is_rejected(): void
    {
        $posting = $this->publishedPosting();
        $user = $this->candidate();

        $this->actingAs($user)->postJson("/api/jobs/postings/{$posting->slug}/applications")->assertCreated();
        $this->actingAs($user)->postJson("/api/jobs/postings/{$posting->slug}/applications")->assertStatus(409);

        $this->assertSame(1, $posting->refresh()->applications_count);
    }

    public function test_a_draft_posting_cannot_be_applied_to(): void
    {
        $posting = JobPosting::factory()->forEmployer($this->employer()->id)->create();

        $this->actingAs($this->candidate())
            ->postJson("/api/jobs/postings/{$posting->slug}/applications")
            ->assertNotFound();
    }

    public function test_a_candidate_sees_only_their_own_applications(): void
    {
        $posting = $this->publishedPosting();
        $mine = $this->candidate('mine@example.test');
        $theirs = $this->candidate('theirs@example.test');

        JobApplication::factory()->forCandidate($mine->id)->create(['job_posting_id' => $posting->id]);
        JobApplication::factory()->forCandidate($theirs->id)->create(['job_posting_id' => $posting->id]);

        $this->actingAs($mine)->getJson('/api/jobs/my-applications')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_candidate_cannot_withdraw_someone_elses_application(): void
    {
        $posting = $this->publishedPosting();
        $mine = $this->candidate('mine@example.test');
        $theirs = $this->candidate('theirs@example.test');

        $application = JobApplication::factory()->forCandidate($theirs->id)->create([
            'job_posting_id' => $posting->id,
        ]);

        $this->actingAs($mine)
            ->postJson("/api/jobs/applications/{$application->id}/withdraw")
            ->assertNotFound();

        $this->assertSame(ApplicationStatus::Submitted, $application->refresh()->status);
    }

    public function test_an_employer_reviews_and_advances_applicants(): void
    {
        $this->app->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());

        $employer = $this->employer();
        $posting = JobPosting::factory()->published()->forEmployer($employer->id)->create();
        $user = $this->candidate();

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
    }

    public function test_an_employer_cannot_set_the_candidate_only_withdrawn_status(): void
    {
        $this->app->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());

        $employer = $this->employer();
        $posting = JobPosting::factory()->published()->forEmployer($employer->id)->create();
        $application = JobApplication::factory()->create(['job_posting_id' => $posting->id]);

        $this->patchJson("/api/jobs/employers/{$employer->id}/applications/{$application->id}", [
            'status' => 'withdrawn',
        ])->assertStatus(422)->assertJsonValidationErrors('status');
    }

    public function test_an_employer_cannot_see_applications_for_another_employer(): void
    {
        $this->app->instance(AuthorizesEmployers::class, new AllowAllEmployerAuthorizer());

        $mine = $this->employer();
        $theirs = $this->employer();

        $posting = JobPosting::factory()->published()->forEmployer($theirs->id)->create();
        $application = JobApplication::factory()->create(['job_posting_id' => $posting->id]);

        $this->getJson("/api/jobs/employers/{$mine->id}/applications")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->patchJson("/api/jobs/employers/{$mine->id}/applications/{$application->id}", [
            'status' => 'hired',
        ])->assertNotFound();
    }
}
