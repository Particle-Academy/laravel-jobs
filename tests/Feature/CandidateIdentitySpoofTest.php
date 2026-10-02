<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature;

use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestUser;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

/**
 * A caller must not be able to BE a candidate by saying so.
 *
 * `CandidateResolver` prefers `$request->user()` and fell back to
 * `$request->input('user_id')` or the `X-Candidate-Id` header, gated on
 * `laravel-jobs.allow_input_user_id` — which **defaulted to true and was absent
 * from the published config file**, so a host could not switch off an option it
 * had never been shown.
 *
 * The candidate routes mount `['api']` and nothing else, because the package
 * cannot assume the host added `auth`. So every ownership check downstream
 * (`forCandidate($resolved)`, and withdraw's
 * `$application->user_id !== $candidateId`) compared the record against an
 * identity THE CALLER SUPPLIED. That is not an authorization check — it is a
 * check that the attacker filled the form in consistently.
 *
 * Reachable unauthenticated against a default install:
 *
 *   GET  /api/jobs/my-applications?user_id=7        -> that candidate's
 *        applications, with resume_path, cover_letter, contact_email and
 *        contact_phone
 *   POST /api/jobs/applications/3/withdraw  {user_id: 7}  -> withdraws it
 *
 * ---------------------------------------------------------------------------
 * Why the existing suite was green
 * ---------------------------------------------------------------------------
 *
 * `AnonymousCandidateTest` covers the anonymous case thoroughly and correctly —
 * and only ever sends NO identity, asserting 401. The fallback it was 401-ing
 * past was never exercised. The suite tested the locked door and not the window
 * beside it, and the 401's own message said *"Authenticate the request or supply
 * user_id"* — the package documenting its own bypass in the response body.
 *
 * Found while building the reference consumer, by reading the resolver to answer
 * an unrelated question about anonymous applications.
 */
class CandidateIdentitySpoofTest extends TestCase
{
    /** The candidate whose data is being reached for. Real row, real id. */
    private function victim(): TestUser
    {
        return TestUser::query()->firstOrCreate(
            ['email' => 'victim@example.test'],
            ['name' => 'Sam Guard'],
        );
    }

    private function application(): JobApplication
    {
        $employer = TestEmployer::query()->create(['name' => 'Acme Security', 'status' => 'approved']);
        $posting = JobPosting::factory()->published()->forEmployer($employer->id)->create();

        return JobApplication::factory()->create([
            'job_posting_id' => $posting->id,
            'user_id' => $this->victim()->getKey(),
            'resume_path' => 'resumes/victim-cv.pdf',
            'cover_letter' => 'Please hire me.',
            'contact_email' => 'victim@example.test',
            'contact_phone' => '555-0100',
        ]);
    }

    public function test_a_user_id_in_the_query_string_does_not_make_you_that_candidate(): void
    {
        $this->application();

        $this->getJson('/api/jobs/my-applications?user_id='.$this->victim()->getKey())->assertStatus(401);
    }

    public function test_a_user_id_in_the_body_does_not_make_you_that_candidate(): void
    {
        $application = $this->application();

        $this->postJson("/api/jobs/applications/{$application->id}/withdraw", ['user_id' => $this->victim()->getKey()])->assertStatus(401);
    }

    public function test_the_candidate_header_does_not_make_you_that_candidate(): void
    {
        $this->application();

        $this->getJson('/api/jobs/my-applications', ['X-Candidate-Id' => (string) $this->victim()->getKey()])->assertStatus(401);
    }

    public function test_no_candidate_data_leaves_the_building(): void
    {
        // The payload is the reason this is a security fix and not a tidy-up. An
        // application row carries a CV path, a cover letter, an email and a phone
        // number, and `my-applications` returns all of them.
        $this->application();

        $body = (string) $this->getJson('/api/jobs/my-applications?user_id='.$this->victim()->getKey())->getContent();

        foreach (['resumes/victim-cv.pdf', 'victim@example.test', '555-0100', 'Please hire me.'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_the_withdrawal_does_not_happen(): void
    {
        // A write, not just a read — and one the candidate and employer both see,
        // without it looking like an attack when they do.
        $application = $this->application();

        $this->postJson("/api/jobs/applications/{$application->id}/withdraw", ['user_id' => $this->victim()->getKey()]);

        $this->assertSame('submitted', $application->fresh()->status->value);
    }

    public function test_the_401_message_no_longer_advertises_the_bypass(): void
    {
        // It read "Authenticate the request or supply user_id." Telling an
        // unauthenticated caller how to become someone is not a helpful error.
        $this->getJson('/api/jobs/my-applications')
            ->assertStatus(401)
            ->assertJsonMissing(['message' => 'Unable to resolve candidate. Authenticate the request or supply user_id.']);

        $message = (string) $this->getJson('/api/jobs/my-applications')->json('message');
        $this->assertStringNotContainsString('user_id', $message);
    }

    public function test_an_authenticated_candidate_still_reads_their_own_applications(): void
    {
        // The other half, every time: a fix that locks out the legitimate user is
        // the same defect in a different coat.
        $application = $this->application();

        $this->actingAs($this->victim())
            ->getJson('/api/jobs/my-applications')
            ->assertOk()
            ->assertJsonPath('data.0.id', $application->id);
    }

    public function test_a_host_may_still_opt_IN_for_server_to_server_callers(): void
    {
        // The capability is not removed, it is turned off. A host that genuinely
        // needs it -- an internal importer behind its own auth -- sets the flag and
        // takes responsibility for who can reach the route.
        config()->set('laravel-jobs.allow_input_user_id', true);

        $application = $this->application();

        $this->getJson('/api/jobs/my-applications?user_id='.$this->victim()->getKey())
            ->assertOk()
            ->assertJsonPath('data.0.id', $application->id);
    }
}
