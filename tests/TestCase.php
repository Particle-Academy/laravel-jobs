<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use ParticleAcademy\LaravelJobs\JobsServiceProvider;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestEmployer;
use ParticleAcademy\LaravelJobs\Tests\Fixtures\TestUser;

/**
 * The suite's Laravel host: a hand-built application on laravel/framework alone
 * (no orchestra/testbench). An empty skeleton in the temp directory, the
 * framework's default config, this package's provider, and in-memory SQLite.
 * Nothing leaves the process.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /** Artisan output is not mocked: that needs Mockery, which this suite does not carry. */
    public $mockConsoleOutput = false;

    public function createApplication(): Application
    {
        $base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'laravel-jobs-tests';
        foreach (['bootstrap/cache', 'storage/framework/views', 'storage/framework/cache', 'storage/logs'] as $dir) {
            if (! is_dir($base.'/'.$dir)) {
                mkdir($base.'/'.$dir, 0777, true);
            }
        }
        // An empty .env: the framework reads one, and a missing file is a warning.
        if (! is_file($base.'/.env')) {
            file_put_contents($base.'/.env', '');
        }

        $app = Application::configure(basePath: $base)
            ->withProviders([JobsServiceProvider::class], withBootstrapProviders: false)
            ->withMiddleware(static function (): void {})
            ->withExceptions(static function (): void {})
            ->create();

        $app->afterBootstrapping(LoadConfiguration::class, function (Application $app): void {
            $config = $app['config'];
            $config->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
            $config->set('app.env', 'testing');
            $config->set('cache.default', 'array');
            $config->set('session.driver', 'array');
            $config->set('mail.default', 'array');
            $config->set('queue.default', 'sync');
            $config->set('logging.default', 'null');
            $config->set('logging.channels.null', ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]);
            $config->set('services.anthropic.key', '');
            $config->set('services.openai.key', '');
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver'   => 'sqlite',
                'database' => ':memory:',
                'prefix'   => '',
            ]);

            // Point the package at this suite's stand-in host models — exactly what
            // a real host does, which keeps the config contract under test.
            $config->set('laravel-jobs.user_model', TestUser::class);
            $config->set('laravel-jobs.employer_model', TestEmployer::class);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->defineDatabaseMigrations();
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
