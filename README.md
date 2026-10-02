# particle-academy/laravel-jobs

[![Fancified](art/fancified.svg)](https://particle.academy)

Laravel package for job boards — employer job postings, a public listing, and
candidate applications. The server half of the Fancy UI job board; the React
half is [`@particle-academy/job-board`](https://github.com/Particle-Academy/job-board).

## Install

```sh
composer require particle-academy/laravel-jobs
php artisan migrate
```

The service provider is auto-discovered. Publish the config to change anything:

```sh
php artisan vendor:publish --tag=laravel-jobs-config
```

## The one thing you must wire up

**This package does not own the employer.** Every host already has its own idea
of the organisation doing the hiring — an agency, a company, a studio — so you
point the package at yours:

```php
// config/laravel-jobs.php
'employer_model' => App\Models\Agency::class,
'user_model'     => App\Models\User::class,
```

Because the employer is yours, the package cannot know who is allowed to act for
one. You tell it, by binding `AuthorizesEmployers`:

```php
use Illuminate\Http\Request;
use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;

$this->app->bind(AuthorizesEmployers::class, fn () => new class implements AuthorizesEmployers {
    public function allows(Request $request, int|string $employerId): bool
    {
        return $request->user()?->agencies()->whereKey($employerId)->exists() ?? false;
    }
});
```

> **The default binding denies everything.** Installing this package grants
> nobody the ability to post as any employer — an unbound install is inert, not
> wide open. Every employer endpoint 403s until you bind your own rule.

## Moderation gate

Hosts usually approve employers before letting them advertise. Name the column
and the value that means "cleared":

```php
'employer_gate' => [
    'column'   => 'status',      // null disables gating entirely
    'approved' => 'approved',
],
```

Drafting is always allowed. **Publishing** is what the gate applies to, so a
pending employer can set everything up while it waits.

### A richer rule

Approval is only the default. Bind `GatesPublishing` to charge per listing,
enforce a plan quota, or anything else:

```php
use ParticleAcademy\LaravelJobs\Contracts\GatesPublishing;
use ParticleAcademy\LaravelJobs\Support\PublishDecision;

class PaidListingGate implements GatesPublishing
{
    public function check(JobPosting $posting): PublishDecision
    {
        if ($this->alreadyPaid($posting)) {
            return PublishDecision::allow();
        }

        return PublishDecision::deny(
            reason: 'Publishing this listing costs $49.',
            code:   'payment_required',
            meta:   ['checkout_url' => $this->checkoutUrl($posting)],
        );
    }
}
```

A denial is deliberately richer than `false`: the `code` and `meta` travel out
to the caller, so your UI can send the employer to checkout rather than just
showing an error. `payment_required` answers **402**; anything else **403**.

Need to render "Publish — $49" without attempting the transition?
`JobPostingService::publishDecision($posting)` asks the gate and changes nothing.

Creating a posting with `status: published` runs the same gate, inside a
transaction — so it cannot be used as a way around `publish()`, and a refusal
leaves no orphan draft.

## API

Mounted at `api/jobs` by default (`routes.prefix`).

### Public — no auth

| | |
|---|---|
| `GET /postings` | The board. Published, unexpired postings only. Supports `search`, `employment_type`, `location`, `is_remote`, `employer_id`, `per_page`. |
| `GET /postings/{slug}` | One posting. A draft, closed or expired posting is a 404 here for everyone. |

### Candidate — resolved from the authenticated user

| | |
|---|---|
| `POST /postings/{slug}/applications` | Apply. |
| `GET /my-applications` | Your own applications. |
| `POST /applications/{application}/withdraw` | Withdraw yours. |

### Employer — gated by `AuthorizesEmployers`

| | |
|---|---|
| `GET\|POST /employers/{employer}/postings` | List (every status) / create. |
| `GET\|PATCH\|DELETE /employers/{employer}/postings/{posting}` | Read / update / delete. |
| `POST .../postings/{posting}/publish\|unpublish\|close` | Status transitions. |
| `GET /employers/{employer}/applications` | Who applied, across your postings. |
| `PATCH /employers/{employer}/applications/{application}` | Advance an applicant. |

## A working consumer you can read

[`laravel-jobs-reference`](https://github.com/Particle-Academy/laravel-jobs-reference) is a real Laravel app that installs this package
from Packagist the way you would, and asserts the things that only break once
there is an app — auth, middleware, status codes, response envelopes, and the host
bindings this package asks for.

Worth reading before you wire your own, in particular:

- **`ResumeDownloadController`** — serving a candidate's CV. This package stores a
  `resume_path` and leaves serving to you, which means the obvious implementation is
  `Storage::url()` on a public disk. That one uses a private disk, authorises every
  request, allows exactly the candidate and the employer the application was sent to
  (**not** admins), and returns **404 rather than 403**, because a 403 confirms an
  application exists at that id.
- **`JobsBindingsProvider`** — the host contracts, both of which refuse when unbound.
  That is this package's best property and its least discoverable one: forget a
  binding and the portal goes quiet rather than wide open.
- **`NotifyEmployerOfApplication`** — the far end of a wire this package cannot
  connect, and why `ShouldQueue` is not enough on its own.

It found three of the defects fixed in 0.3.0, 0.4.0 and 0.5.0 — two of them
invisible to a green suite here.

## Design notes

- **Status transitions are not field updates.** `PATCH` silently drops `status`;
  publishing and closing go through their own endpoints so the gate and the
  events cannot be bypassed by smuggling a field.
- **Another employer's records 404, they do not 403.** Whether a posting exists
  is not something a competitor should be able to probe for.
- **`withdrawn` belongs to the candidate.** It is absent from the statuses an
  employer may assign.
- Domain refusals carry their own HTTP status (`403` not approved, `409`
  duplicate application, `422` applications closed) via a `render()` on the
  exception, so no host-side handler wiring is needed.

## Events

`JobPostingPublished`, `JobPostingClosed`, `ApplicationSubmitted`,
`ApplicationStatusChanged` — for notifications, search indexing, and the like.

## Tests

```sh
composer install && vendor/bin/phpunit
```

The suite configures the package against its own stand-in host models, so the
`employer_model` / `user_model` contract is exercised the way a real host uses it.

## License

MIT.
