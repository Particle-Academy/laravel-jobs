<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use ParticleAcademy\LaravelJobs\JobsServiceProvider;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestUser;

abstract class TestCase extends Orchestra
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [JobsServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Point the package at this suite's stand-in host models — exactly what
        // a real host does, which keeps the config contract under test.
        $app['config']->set('laravel-jobs.user_model', TestUser::class);
        $app['config']->set('laravel-jobs.employer_model', TestEmployer::class);
    }

    /** The host owns these tables, so the suite has to create them itself. */
    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->timestamps();
        });

        Schema::create('employers', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
}
