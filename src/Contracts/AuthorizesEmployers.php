<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Contracts;

use Illuminate\Http\Request;

/**
 * Decides whether the current request may act on behalf of an employer.
 *
 * The employer model belongs to the host application, so this package has no
 * way to know who owns one. Rather than trust whoever mounted the routes, it
 * asks the host — and the default binding denies everything, so an unbound
 * install is inert instead of wide open.
 *
 * Bind your own in a service provider:
 *
 *   $this->app->bind(AuthorizesEmployers::class, fn () => new class implements AuthorizesEmployers {
 *       public function allows(Request $request, int|string $employerId): bool
 *       {
 *           return $request->user()?->agencies()->whereKey($employerId)->exists() ?? false;
 *       }
 *   });
 */
interface AuthorizesEmployers
{
    public function allows(Request $request, int|string $employerId): bool;
}
