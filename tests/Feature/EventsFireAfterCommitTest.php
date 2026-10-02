<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature;

use Illuminate\Support\Facades\Event;
use ParticleAcademy\LaravelJobs\Enums\ApplicationStatus;
use ParticleAcademy\LaravelJobs\Events\ApplicationSubmitted;
use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Services\ApplicationService;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\TestCase;
use RuntimeException;

/**
 * A listener that fails must not destroy the candidate's application.
 *
 * `ApplicationService::submit()` dispatches `ApplicationSubmitted` from INSIDE
 * its `DB::transaction()`. Measured before the fix, with one listener that throws:
 *
 *     job_applications rows        = 0
 *     posting.applications_count   = 0
 *
 * The application was gone. So a host whose notification listener hit a dead SMTP
 * server, or had any bug at all, silently lost applications — the candidate saw a
 * failure, the employer saw nothing, and the system looked like it had no
 * applicants. A host cannot fix that from the outside without wrapping every
 * listener it ever writes in a try/catch and never forgetting.
 *
 * The four events now implement `ShouldDispatchAfterCommit`, which is also the
 * correct semantics: `ApplicationSubmitted` asserts that an application WAS
 * submitted, and that is not true until the transaction commits. If it rolls back,
 * the event should never have fired.
 *
 * ---------------------------------------------------------------------------
 * Why the first test here is the load-bearing one
 * ---------------------------------------------------------------------------
 *
 * `ShouldDispatchAfterCommit` defers dispatch to commit — and `RefreshDatabase`
 * wraps every test in a transaction that is ROLLED BACK and never commits. So the
 * obvious failure mode of this fix is that the events stop firing entirely, in
 * production as well as in tests, and nothing complains: no error, no listener, no
 * assertion. The package would simply go quiet.
 *
 * Deferring an event is therefore not a change you make and then run a green
 * suite over. The suite was green with the events never firing, because nothing in
 * it asserted that they do.
 */
class EventsFireAfterCommitTest extends TestCase
{
    private function publishedPosting(): JobPosting
    {
        $employer = TestEmployer::query()->create(['name' => 'Acme Security', 'status' => 'approved']);

        return JobPosting::factory()->published()->forEmployer($employer->id)->create();
    }

    public function test_the_event_still_fires_from_the_real_service_path(): void
    {
        // Not Event::fake(). A fake records a dispatch attempt and would pass even
        // if the after-commit deferral swallowed it forever.
        $heard = [];
        Event::listen(ApplicationSubmitted::class, function (ApplicationSubmitted $e) use (&$heard): void {
            $heard[] = $e->application->id;
        });

        $application = (new ApplicationService())->submit($this->publishedPosting(), 42);

        $this->assertSame(
            [$application->id],
            $heard,
            'ApplicationSubmitted must still reach listeners -- a deferred event that never fires is silent',
        );
    }

    public function test_a_throwing_listener_does_not_destroy_the_application(): void
    {
        // The reason for the change. Before it, this left zero rows.
        Event::listen(ApplicationSubmitted::class, function (): void {
            throw new RuntimeException('the mail server is down');
        });

        $posting = $this->publishedPosting();

        try {
            (new ApplicationService())->submit($posting, 42);
        } catch (RuntimeException) {
            // A listener blowing up still surfaces. What must NOT happen is the
            // candidate's application disappearing with it.
        }

        $this->assertSame(1, JobApplication::query()->count());
        $this->assertSame(1, $posting->fresh()->applications_count);

        $application = JobApplication::query()->sole();
        $this->assertSame(ApplicationStatus::Submitted, $application->status);
    }

    public function test_the_status_change_event_fires_too(): void
    {
        // changeStatus() is not inside a transaction, so this one is a guard
        // against the deferral breaking the un-transacted path.
        $application = (new ApplicationService())->submit($this->publishedPosting(), 42);

        $heard = 0;
        Event::listen(\ParticleAcademy\LaravelJobs\Events\ApplicationStatusChanged::class, function () use (&$heard): void {
            $heard++;
        });

        (new ApplicationService())->changeStatus($application, ApplicationStatus::Reviewing);

        $this->assertSame(1, $heard);
    }
}
