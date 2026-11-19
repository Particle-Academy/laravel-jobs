<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Feature\EmployerGateColumnLoadingTest;

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

beforeEach(function () {
    config()->set('laravel-jobs.employer_model', TestEmployer::class);
    config()->set('laravel-jobs.employer_gate.column', 'status');
    config()->set('laravel-jobs.employer_gate.approved', 'approved');
});

function gate(): EmployerGate {
    return new EmployerGate();
}

test('a fully loaded unapproved employer is denied', function () {
    $employer = TestEmployer::query()->create(['name' => 'Pending Co', 'status' => 'pending']);

    $this->assertFalse(gate()->allowsPublishing($employer));
});

test('a fully loaded approved employer is allowed', function () {
    $employer = TestEmployer::query()->create(['name' => 'Good Co', 'status' => 'approved']);

    $this->assertTrue(gate()->allowsPublishing($employer));
});

test('a select that omits the gate column does NOT bypass the gate', function () {
    // THE BUG. A host narrowing a query for performance -- elsewhere, long
    // after the gate was configured and tested -- moved from "moderation
    // enforced" to "moderation off" with no signal at any layer.
    TestEmployer::query()->create(['name' => 'Pending Co', 'status' => 'pending']);

    $narrowed = TestEmployer::query()->select(['id', 'name'])->first();

    $this->assertArrayNotHasKey('status', $narrowed->getAttributes(), 'the fixture must not load the column, or this asserts nothing');
    $this->assertFalse(gate()->allowsPublishing($narrowed), 'a select() must not disable moderation');
});

test('a narrowed APPROVED employer is still allowed', function () {
    // The fix must not simply deny whenever the column is absent: that would
    // trade a silent bypass for a silent lockout, and a host narrowing a
    // query would find every publish refused instead.
    TestEmployer::query()->create(['name' => 'Good Co', 'status' => 'approved']);

    $narrowed = TestEmployer::query()->select(['id', 'name'])->first();

    $this->assertTrue(gate()->allowsPublishing($narrowed));
});

test('an id is still resolved and gated', function () {
    $employer = TestEmployer::query()->create(['name' => 'Pending Co', 'status' => 'pending']);

    $this->assertFalse(gate()->allowsPublishing($employer->id));
});

test('a column the table does not have stays FORGIVING', function () {
    // The original intent, preserved. A host that configures a column its
    // employer model genuinely does not have is misconfigured, and refusing
    // every publish would be baffling rather than informative. The
    // difference from the bug is "the table has no such column" versus "this
    // instance did not load one that exists" -- the first is the host's
    // mistake, the second was ours.
    config()->set('laravel-jobs.employer_gate.column', 'no_such_column');

    $employer = TestEmployer::query()->create(['name' => 'Any Co', 'status' => 'pending']);

    $this->assertTrue(gate()->allowsPublishing($employer));
});

test('an unconfigured gate allows everyone', function () {
    config()->set('laravel-jobs.employer_gate.column', null);

    $employer = TestEmployer::query()->create(['name' => 'Pending Co', 'status' => 'pending']);

    $this->assertTrue(gate()->allowsPublishing($employer));
});

test('a missing employer is denied', function () {
    $this->assertFalse(gate()->allowsPublishing(999999));
    $this->assertFalse(gate()->allowsPublishing(null));
});

test('a row deleted under a narrowed instance is denied', function () {
    // Fail CLOSED. The instance says nothing about approval and the row is
    // gone, so there is nothing that could authorise a publish.
    TestEmployer::query()->create(['name' => 'Gone Co', 'status' => 'approved']);

    $narrowed = TestEmployer::query()->select(['id', 'name'])->first();
    TestEmployer::query()->whereKey($narrowed->getKey())->delete();

    $this->assertFalse(gate()->allowsPublishing($narrowed));
});
