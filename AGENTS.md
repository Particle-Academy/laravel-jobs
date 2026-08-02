# AGENTS.md — laravel-jobs

Job postings and applications for Laravel. API-only: the host owns the user and
employer models. `CLAUDE.md` symlinks here.

The React surface is `@particle-academy/job-board`. It is not required — the
package is usable headlessly.

## The shape

```
job_posting ── job_application
   │                 │
employer          user (both host-owned, resolved from config)
```

- **The package owns no user or organisation table.** `jobs.user_model` and
  `jobs.employer_model` name the host's classes. That is what makes it droppable
  into an app that already has accounts.
- **Two services carry the behaviour:** `JobPostingService` (create / update /
  publish / unpublish / close) and `ApplicationService` (submit / changeStatus /
  withdraw). Controllers are thin.
- **Three enums:** `JobPostingStatus` (draft, published, closed),
  `EmploymentType` (6 cases), `ApplicationStatus` (submitted → reviewing →
  shortlisted → rejected / hired / withdrawn).

## Rules

- **Deny by default, and never invert it.** Two contracts gate this package, and
  both refuse when unbound:
  - `AuthorizesEmployers::allows(Request, employerId)` — may this request act
    for that employer? The package cannot know who owns an employer row, so it
    asks the host.
  - `GatesPublishing::check(JobPosting): PublishDecision` — may this posting go
    live? Drafting is never gated; only publishing is.

  **Removing a host binding must switch the feature OFF, not open it up.** This
  is the shape `laravel-courses` was retrofitted to after shipping wide open —
  do not regress it here.
- **`PublishDecision` carries a `code` and `meta` on denial** so a host's own UI
  can react — send the employer to checkout, show a quota — rather than printing
  a generic error. Keep denials informative.
- **Anonymous applications are supported deliberately.** `ApplicationService::submit()`
  takes `int|string $userId`, and the suite covers the anonymous case. Don't
  assume an authenticated applicant.
- **Status transitions are the audit trail.** `changeStatus()` takes optional
  notes; a candidate's history is why the enum has both `rejected` and
  `withdrawn` rather than one terminal state.

## Testing

```bash
composer install
vendor/bin/phpunit
```

`tests/TestCase.php` runs on `orchestra/testbench` with in-memory SQLite, creates
the host-owned `users` and `employers` tables itself, and points the package's
config at `Tests\Fixtures\TestUser` / `TestEmployer`. That is what keeps the
host-model config an actual contract rather than a comment.
`AllowAllEmployerAuthorizer` exists so tests can opt *in* to permission —
the default still denies.

## Publishing

PHP package — Packagist auto-syncs from git tags. Ship = bump → CHANGELOG in the
same commit → tag `vX.Y.Z` → push tag. Then advance the envelope pin. See the
envelope's `.ai/knowledge/publishing.md`.
