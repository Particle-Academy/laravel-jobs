<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;
use ParticleAcademy\LaravelJobs\Contracts\GatesPublishing;
use ParticleAcademy\LaravelJobs\Services\ApplicationService;
use ParticleAcademy\LaravelJobs\Services\JobPostingService;
use ParticleAcademy\LaravelJobs\Support\ApprovalPublishGate;
use ParticleAcademy\LaravelJobs\Support\CandidateResolver;
use ParticleAcademy\LaravelJobs\Support\DenyAllEmployerAuthorizer;
use ParticleAcademy\LaravelJobs\Support\EmployerGate;

class JobsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/laravel-jobs.php', 'laravel-jobs');

        $this->app->singleton(EmployerGate::class);
        $this->app->singleton(CandidateResolver::class);

        // Deny-by-default. Hosts override this binding with their own ownership
        // rule; until they do, every employer endpoint refuses.
        $this->app->singletonIf(AuthorizesEmployers::class, DenyAllEmployerAuthorizer::class);

        // Publishing defaults to the employer_gate approval column. Hosts with a
        // richer rule — paid listings, plan quotas — bind their own.
        $this->app->singletonIf(GatesPublishing::class, ApprovalPublishGate::class);
        $this->app->singleton(JobPostingService::class);
        $this->app->singleton(ApplicationService::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->registerRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/laravel-jobs.php' => config_path('laravel-jobs.php'),
            ], 'laravel-jobs-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => database_path('migrations'),
            ], 'laravel-jobs-migrations');
        }
    }

    private function registerRoutes(): void
    {
        if (! (bool) config('laravel-jobs.routes.enabled', true)) {
            return;
        }

        Route::group([
            'prefix'     => (string) config('laravel-jobs.routes.prefix', 'api/jobs'),
            'middleware' => (array) config('laravel-jobs.routes.middleware', ['api']),
        ], function (): void {
            $this->loadRoutesFrom(__DIR__.'/../routes/api.php');
        });
    }
}
