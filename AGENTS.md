# Using particle-academy/laravel-jobs — guide for agents

How an agent installs and uses this package in a consumer app. What the package
is, is in [README.md](./README.md). Notes for working ON this repo's code are in
[CONTRIBUTING.md](./CONTRIBUTING.md).

## Install

```bash
composer require particle-academy/laravel-jobs
php artisan migrate
php artisan vendor:publish --tag=laravel-jobs-config
```

- **Requires:** PHP `^8.4`, Laravel `^13.0`. The service provider is
  auto-discovered; routes mount at `api/jobs` (`routes.prefix`).
- **Point it at your models** in `config/laravel-jobs.php`: `employer_model`
  and `user_model`. The package owns no user or organisation table.
- **Bind `AuthorizesEmployers`** in a service provider. Until you do, every
  employer endpoint answers 403.
- The React half is `@particle-academy/job-board`; it is optional.

## Find it on the Fancy MCP server

The catalog server is `https://ui.particle.academy/mcp` (streamable HTTP, no API
key; MCP id `fancy-ui`). This package is not yet listed there; it appears once
it is in the registry. Server-side packages are found with
`search-backend-packages` (stack `php`). The React half is listed:
`search-components` ("job board") finds the `job-board` entries.

## API essentials

- **Services:** `JobPostingService` (create / update / publish / unpublish /
  close, plus `publishDecision($posting)`, which asks the gate and changes
  nothing) and `ApplicationService` (submit / changeStatus / withdraw).
- **Host contracts:** `AuthorizesEmployers::allows(Request, $employerId)` and
  `GatesPublishing::check(JobPosting): PublishDecision`. Both deny when unbound.
- **Moderation gate:** `employer_gate.column` / `employer_gate.approved` (column
  `null` disables gating). Drafting is never gated; publishing is.
- **`PublishDecision::deny(reason:, code:, meta:)`** carries `code` and `meta`
  to the caller; `payment_required` answers 402, anything else 403.
- **Events:** `JobPostingPublished`, `JobPostingClosed`, `ApplicationSubmitted`,
  `ApplicationStatusChanged`.
- **Endpoints:** public (`GET /postings`, `GET /postings/{slug}`), candidate
  (apply, `my-applications`, withdraw) and employer
  (`/employers/{employer}/...`). The full table is in the README.

## Traps

- **An unbound install is inert, not open.** Removing a binding switches the
  feature off. Do not work around a 403 by binding an allow-all authorizer in
  production.
- **There are no anonymous applications.** Every application belongs to a
  user id, and an unauthenticated apply answers 401. `allow_input_user_id`
  (default `false`) takes the id from the request and trusts the caller
  completely.
- **`PATCH` silently drops `status`.** Publish, unpublish and close go through
  their own endpoints so the gate and events cannot be bypassed.
- **Another employer's records 404, not 403**, and `withdrawn` can only be set
  by the candidate.
- **Résumés are yours to serve.** The package stores a `resume_path`; serve it
  from a private disk with per-request authorisation, not `Storage::url()` on a
  public disk. `ResumeDownloadController` in
  [`laravel-jobs-reference`](https://github.com/Particle-Academy/laravel-jobs-reference)
  shows how.
