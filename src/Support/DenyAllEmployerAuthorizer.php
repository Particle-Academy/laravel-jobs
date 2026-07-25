<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;

/**
 * The default authorizer: refuses everything.
 *
 * Secure by default — installing this package does not hand anyone the ability
 * to post jobs as any employer. The host must bind its own implementation. The
 * warning fires once per request so the reason is discoverable rather than a
 * silent 403.
 */
class DenyAllEmployerAuthorizer implements AuthorizesEmployers
{
    private bool $warned = false;

    public function allows(Request $request, int|string $employerId): bool
    {
        if (! $this->warned) {
            $this->warned = true;

            Log::warning(
                'laravel-jobs: no AuthorizesEmployers implementation is bound, so every employer '
                .'action is denied. Bind ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers '
                .'in a service provider to enable the employer endpoints.',
            );
        }

        return false;
    }
}
