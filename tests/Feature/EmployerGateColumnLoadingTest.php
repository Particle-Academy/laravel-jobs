<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature;

use ParticleAcademy\LaravelJobs\Support\EmployerGate;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\TestCase;

/**
 * The gate must not be disabled by a `select()`.
 *
 * `allowsPublishing()` treated a gate column missing from the instance as
 * UNGATED and returned `true`. The reasoning was sound — a host that configures
 * a column its employer model does not have should not have every publish
 * silently refused — but `getAttributes()` returns **what was loaded on that
 * instance**, not what the table has. So an ordinary `select()` narrowing a
 * query for performance, anywhere in a host's code, silently switched moderation
 * off.
 *
 * Reported by the GuardCard team as laravel-jobs#6, measured against the
 * released package. **This is the dangerous shape**, and it is worth naming:
 * every other sharp edge in this package is LOUD. Forget a binding and the
 * portal is dead — you know in seconds. This one failed OPEN, silently, and
 * looked like success: an unapproved employer's posting went live and the system
 * reported that it worked. Nothing logged, nothing threw, and no
 * `PublishDecision` denial existed to inspect.
 *
 * It also contradicted the package's own stated philosophy. The README is
 * explicit that removing a host binding must switch a feature OFF rather than
 * open it up, and `AuthorizesEmployers` / `GatesPublishing` both honour that.
 * This path did the opposite.
 *
 * They were not bitten, and said so plainly: their own `GatesPublishing` checks
 * approval first because money must never buy past moderation, so they never
 * relied on this default. Their safety was an accident of having their own
 * reason to re-check — not evidence the default was safe. A host that used the
 * shipped default was exposed.
 */
class EmployerGateColumnLoadingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('laravel-jobs.employer_model', TestEmployer::class);
        config()->set('laravel-jobs.employer_gate.column', 'status');
        config()->set('laravel-jobs.employer_gate.approved', 'approved');
    }

    private function gate(): EmployerGate
    {
        return new EmployerGate();
    }

    public function test_a_fully_loaded_unapproved_employer_is_denied(): void
    {
        $employer = TestEmployer::query()->create(['name' => 'Pending Co', 'status' => 'pending']);

        $this->assertFalse($this->gate()->allowsPublishing($employer));
    }

    public function test_a_fully_loaded_approved_employer_is_allowed(): void
    {
        $employer = TestEmployer::query()->create(['name' => 'Good Co', 'status' => 'approved']);

        $this->assertTrue($this->gate()->allowsPublishing($employer));
    }

    public function test_a_select_that_omits_the_gate_column_does_NOT_bypass_the_gate(): void
    {
        // THE BUG. A host narrowing a query for performance -- elsewhere, long
        // after the gate was configured and tested -- moved from "moderation
        // enforced" to "moderation off" with no signal at any layer.
        TestEmployer::query()->create(['name' => 'Pending Co', 'status' => 'pending']);

        $narrowed = TestEmployer::query()->select(['id', 'name'])->first();

        $this->assertArrayNotHasKey('status', $narrowed->getAttributes(), 'the fixture must not load the column, or this asserts nothing');
        $this->assertFalse($this->gate()->allowsPublishing($narrowed), 'a select() must not disable moderation');
    }

    public function test_a_narrowed_APPROVED_employer_is_still_allowed(): void
    {
        // The fix must not simply deny whenever the column is absent: that would
        // trade a silent bypass for a silent lockout, and a host narrowing a
        // query would find every publish refused instead.
        TestEmployer::query()->create(['name' => 'Good Co', 'status' => 'approved']);

        $narrowed = TestEmployer::query()->select(['id', 'name'])->first();

        $this->assertTrue($this->gate()->allowsPublishing($narrowed));
    }

    public function test_an_id_is_still_resolved_and_gated(): void
    {
        $employer = TestEmployer::query()->create(['name' => 'Pending Co', 'status' => 'pending']);

        $this->assertFalse($this->gate()->allowsPublishing($employer->id));
    }

    public function test_a_column_the_table_does_not_have_stays_FORGIVING(): void
    {
        // The original intent, preserved. A host that configures a column its
        // employer model genuinely does not have is misconfigured, and refusing
        // every publish would be baffling rather than informative. The
        // difference from the bug is "the table has no such column" versus "this
        // instance did not load one that exists" -- the first is the host's
        // mistake, the second was ours.
        config()->set('laravel-jobs.employer_gate.column', 'no_such_column');

        $employer = TestEmployer::query()->create(['name' => 'Any Co', 'status' => 'pending']);

        $this->assertTrue($this->gate()->allowsPublishing($employer));
    }

    public function test_an_unconfigured_gate_allows_everyone(): void
    {
        config()->set('laravel-jobs.employer_gate.column', null);

        $employer = TestEmployer::query()->create(['name' => 'Pending Co', 'status' => 'pending']);

        $this->assertTrue($this->gate()->allowsPublishing($employer));
    }

    public function test_a_missing_employer_is_denied(): void
    {
        $this->assertFalse($this->gate()->allowsPublishing(999999));
        $this->assertFalse($this->gate()->allowsPublishing(null));
    }

    public function test_a_row_deleted_under_a_narrowed_instance_is_denied(): void
    {
        // Fail CLOSED. The instance says nothing about approval and the row is
        // gone, so there is nothing that could authorise a publish.
        TestEmployer::query()->create(['name' => 'Gone Co', 'status' => 'approved']);

        $narrowed = TestEmployer::query()->select(['id', 'name'])->first();
        TestEmployer::query()->whereKey($narrowed->getKey())->delete();

        $this->assertFalse($this->gate()->allowsPublishing($narrowed));
    }
}
