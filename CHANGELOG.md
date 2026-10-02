# Changelog

Notable changes to `particle-academy/laravel-jobs`.

**BREAKING** marks anything that can stop working on upgrade. This package is
pre-1.0, so breaking changes land in MINOR releases — read those entries before
upgrading.

---

## [0.5.0] - 2026-10-02

### Fixed

- **BREAKING (timing) — a failing listener destroyed the candidate's
  application.** The four events now implement `ShouldDispatchAfterCommit`.

  `ApplicationService::submit()` dispatches `ApplicationSubmitted` from INSIDE its
  `DB::transaction()`. Measured against 0.4.0 with a single listener that throws:

  ```
  job_applications rows       = 0
  posting.applications_count  = 0
  ```

  The application was gone. A host whose notification listener hit a dead SMTP
  server — or had any bug at all — silently lost applications. The candidate saw a
  failure, the employer saw nothing, and the system was indistinguishable from one
  with no applicants. A host could not fix this from outside without wrapping every
  listener it ever writes in a try/catch, and never once forgetting.

  Deferring to commit is also the correct semantics: `ApplicationSubmitted` asserts
  that an application *was* submitted, which is not true until the transaction
  commits. If it rolls back, the event should never have fired.

  **What you must DO.** Almost certainly nothing. The events fire as before, a
  moment later, and still synchronously within the request.

  - **Your listener only reads the record, mails, queues a job or calls a
    webhook** → nothing. This is strictly better: your failure can no longer take
    the application with it.
  - **Your listener writes in the same transaction and relies on rolling back
    together with the application** → it no longer will. The event fires after
    commit, so your write is a separate unit. This is the one case that changes
    behaviour, and if you are in it you almost certainly wanted
    `DB::transaction()` in the listener instead.
  - **You assert on event dispatch in a test using `Event::fake()`** → still
    passes; faking intercepts before the deferral.

  Note the trap this fix walks into, because it is also the reason the test exists:
  `ShouldDispatchAfterCommit` defers dispatch to commit, and `RefreshDatabase`
  wraps every test in a transaction that is rolled back and never commits. The
  obvious failure mode is the events quietly never firing, in production as well
  as in tests, with nothing to complain — no error, no listener, no assertion.
  `EventsFireAfterCommitTest` asserts a real listener on the real service path
  actually hears the event, and that test fails if the deferral ever swallows it.

## [0.4.0] - 2026-10-02

### Security

- **BREAKING — an unauthenticated caller could BE any candidate by saying so.**
  `allow_input_user_id` now defaults to `false`.

  `CandidateResolver` preferred `$request->user()` and fell back to a `user_id` in
  the request body/query or an `X-Candidate-Id` header. That fallback was gated on
  `laravel-jobs.allow_input_user_id`, which **defaulted to `true` and was not in
  the published config file** — so a host could not switch off an option it had
  never been shown.

  The candidate routes mount `routes.middleware`, `['api']` by default with no
  `auth`, because the package cannot know how a host authenticates. So on a
  default install, measured against 0.3.0 and unauthenticated:

  ```
  GET  /api/jobs/my-applications?user_id=7
       -> 200, that candidate's applications, including resume_path,
          cover_letter, contact_email and contact_phone
  POST /api/jobs/applications/3/withdraw   {"user_id": 7}
       -> 200, and their application is withdrawn
  ```

  The `X-Candidate-Id` header did the same. The ownership checks downstream are
  real — `forCandidate($resolved)`, and withdraw's
  `$application->user_id !== $candidateId` — but they compare the record against
  whatever identity arrived. They verify that the caller was *consistent*, not
  that they are *who they claim*.

  **How bad it was for you depended on `routes.middleware`, and the conventional
  choice was the dangerous one.** The default is `['api']`, which is stateless and
  carries no CSRF — so on the documented default, **the write vector was live**.
  A host that mounted these on `web` instead got CSRF, and every write was
  blocked (419) regardless of package version, leaving read-only disclosure.

  That distinction was established by a consumer who measured all three vectors on
  their own running site and corrected their own first report when the write came
  back 419. Their point is the one to carry: the severity varied by a mounting
  choice that has nothing to do with authentication, and hosts who did the normal
  thing for an API were the most exposed. CSRF, where it helped, was not a control
  anyone chose for this surface — it fell out of an unrelated decision, and
  crediting it as deliberate protection lasts exactly until someone changes that
  line.

  **What you must DO.** Almost certainly nothing:

  - **Your routes have `auth` on them** (via `routes.middleware`) → nothing. An
    authenticated user never reached the fallback; `$request->user()` wins.
  - **You are an ordinary web/Inertia app using session auth** → nothing.
  - **You deliberately pass `user_id` from a server-to-server caller or your own
    tests** → set `LARAVEL_JOBS_ALLOW_INPUT_USER_ID=true`, or
    `'allow_input_user_id' => true` in the published config, **and** make sure
    that route is not anonymously reachable. The capability is unchanged; it is
    now opt-in.
  - **You are not sure** → the exposure is exactly "the candidate endpoints are
    reachable without authentication". `php artisan route:list --path=api/jobs`
    and look for `auth`.

  **You do NOT need to re-publish the config for the fix to apply.** The default
  lives in code, and a published config predating 0.4.0 simply has no key —
  verified in a real app: `config('laravel-jobs.allow_input_user_id')` returns
  `false` with the old file in place. Re-publish only if you want to READ the new
  block or opt in:
  `php artisan vendor:publish --tag=laravel-jobs-config --force`.

- **The 401 no longer tells the caller how to spoof a candidate.** It read
  *"Unable to resolve candidate. Authenticate the request or supply user_id."* —
  the package documenting its own bypass in the response body. It now reads
  *"Unable to resolve the candidate for this request."*

  If you assert on that string, update it. Nothing else reads it.

### Added

- **A `composer test` script.** There was none, so `release-preflight` ran zero
  commands against this package and still reported "its own checks pass" — on a
  security release. The suite existed and was green the whole time; nothing
  automated was running it. An absent script makes a test step pass by doing
  nothing, which is the one defect that hides itself.

### Changed

- `AGENTS.md` no longer claims anonymous applications are "supported
  deliberately". Every application belongs to a user id and always did; what was
  actually supported was taking that id from an untrusted request. The sentence
  is how the hole read as a feature for two releases — and it cited the suite,
  whose anonymous tests assert **401**. The doc and the tests disagreed and the
  doc won the resolver's design.

### Why the suite was green

`AnonymousCandidateTest` covers the anonymous case carefully and correctly, and
only ever sends **no** identity. The fallback it was 401-ing past was never
exercised. It tested the locked door, not the window beside it.

`CandidateIdentitySpoofTest` is the window. Six of its eight tests fail against
0.3.0.

Found while building the reference consumer app, by reading the resolver to
answer an unrelated question about anonymous applications.

## [0.3.0] - 2026-10-02

### Security

- **The publish gate failed OPEN when the gate column was not loaded on the
  employer instance.** An ordinary `select()` silently switched moderation off.

  `EmployerGate::allowsPublishing()` treated a gate column missing from the model
  as *ungated* and returned `true`. The intent was sound and the comment said so:
  a host that configures a column its employer model does not have should not have
  every publish baffingly refused. But **`getAttributes()` returns what was LOADED
  on that instance, not what the table has** — so a host that configured the gate
  correctly, tested it correctly, and later narrowed an unrelated query for
  performance moved from "moderation enforced" to "moderation off" with no signal
  at any layer.

  Measured by the reporter against the released package, with
  `column => 'status'`, `approved => 'approved'`, on an employer whose status was
  `pending`:

  | passed to `allowsPublishing()` | before | after |
  |---|---|---|
  | the full model | `false` | `false` |
  | `select(['id','name','user_id'])` | **`true`** — bypassed | `false` |
  | the id | `false` | `false` |

  **Why this one mattered more than its severity suggests.** Every other sharp
  edge in this package is LOUD: forget a host binding and the portal is dead, and
  you know in seconds. This failed **open**, **silently**, and **looked like
  success** — an unapproved employer's posting went live, nothing logged, nothing
  threw, and there was no `PublishDecision` denial to inspect. It also contradicted
  the rule this package states outright, that removing a host binding switches a
  feature OFF rather than opening it up.

  **The two cases are now told apart**, which is what the original comment was
  reaching for:

  - **The instance did not load a column the table has** → the column is read from
    the database. One extra query, and only in the case that used to be silently
    wrong.
  - **The table has no such column** → still forgiving, because refusing every
    publish would be baffling rather than informative — but it now logs a warning
    once per table/column, so a misconfiguration is not silent either.

  A row that is gone, or hidden by a global scope such as a soft delete, denies.
  A gate that cannot verify itself fails closed.

  **What you must do: nothing, but check whether it changes your behaviour.** If
  you pass narrowed employer models anywhere, publishes that previously succeeded
  may now correctly be denied. That is the bug being fixed, not a regression. If
  you see the new warning, `employer_gate.column` names a column your employer
  table does not have — set it to a real column, or to `null` to turn gating off
  deliberately.

  Reported as **#6** by the GuardCard team, who found it while answering a question
  about something else and were explicit that they had not been bitten: their own
  `GatesPublishing` checks approval first because money must never buy past
  moderation, so they never relied on this default. Their safety was an accident of
  having their own reason to re-check, not evidence the default was safe.


## [Unreleased]

## 0.2.0 — 2026-08-07

### Changed

- **BREAKING — PHP 8.3 is no longer supported.** `require.php` moves from `^8.3` to `^8.4`.

  **What you must do:** on PHP 8.4 or newer, nothing. On 8.3, either upgrade PHP first or stay on the previous release — it keeps working and is unaffected by this.

- CI now tests PHP 8.4 only, instead of a matrix spanning versions this package no longer claims to support. A matrix that tests what the manifest forbids is worse than none — it reports green for a combination nobody can install.

### Why

These are the kit 0.5 platform floors. The suite was split across PHP 8.2 and 8.3 with the framework spanning 11–13, so no package could rely on anything newer than its weakest sibling. Every PHP package in the kit takes the same floors at once, so a consumer never has to resolve a mix.

Pre-1.0, so this lands in a MINOR. **No API changed, nothing was removed, nothing was renamed** — only what the package requires.


## 0.1.0 — 2026-08-01

**First published release.** Job postings and applications for Laravel, with host-supplied employer/user models and deny-by-default authorization (`AuthorizesEmployers`, `GatesPublishing`) — removing a host binding switches the feature off rather than opening it up.

### Added

- **CI** — matching the rest of the Fancy kit.
- This changelog. Entries start here rather than being reconstructed after the
  fact: the reasoning behind the earlier commits has already evaporated, and
  inventing it would be worse than admitting the gap.

