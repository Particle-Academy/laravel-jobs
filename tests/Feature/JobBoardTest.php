<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature;

use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

class JobBoardTest extends TestCase
{
    private function employer(string $status = 'approved'): TestEmployer
    {
        return TestEmployer::query()->create(['name' => 'Acme Security', 'status' => $status]);
    }

    public function test_the_board_lists_only_published_postings(): void
    {
        $employer = $this->employer();

        JobPosting::factory()->published()->forEmployer($employer->id)->create(['title' => 'Visible role']);
        JobPosting::factory()->forEmployer($employer->id)->create(['title' => 'Draft role']);
        JobPosting::factory()->closed()->forEmployer($employer->id)->create(['title' => 'Closed role']);

        $response = $this->getJson('/api/jobs/postings')->assertOk();

        $titles = array_column($response->json('data'), 'title');

        $this->assertSame(['Visible role'], $titles);
    }

    public function test_expired_postings_drop_off_the_board(): void
    {
        $employer = $this->employer();
        JobPosting::factory()->expired()->forEmployer($employer->id)->create();

        $this->getJson('/api/jobs/postings')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_draft_posting_is_not_reachable_by_slug(): void
    {
        $employer = $this->employer();
        $posting = JobPosting::factory()->forEmployer($employer->id)->create();

        $this->getJson("/api/jobs/postings/{$posting->slug}")->assertNotFound();
    }

    public function test_the_board_can_be_searched_and_filtered(): void
    {
        $employer = $this->employer();

        JobPosting::factory()->published()->forEmployer($employer->id)->create([
            'title' => 'Overnight patrol guard', 'location' => 'Torrance, CA', 'is_remote' => false,
        ]);
        JobPosting::factory()->published()->forEmployer($employer->id)->create([
            'title' => 'Remote dispatcher', 'location' => 'Anywhere', 'is_remote' => true,
        ]);

        $this->getJson('/api/jobs/postings?search=patrol')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/jobs/postings?is_remote=1')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/jobs/postings?location=Torrance')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_slugs_are_generated_and_disambiguated(): void
    {
        $employer = $this->employer();

        $a = JobPosting::query()->create(['employer_id' => $employer->id, 'title' => 'Security Guard']);
        $b = JobPosting::query()->create(['employer_id' => $employer->id, 'title' => 'Security Guard']);

        $this->assertSame('security-guard', $a->slug);
        $this->assertSame('security-guard-2', $b->slug);
    }
}
