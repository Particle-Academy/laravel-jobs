<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TestUser extends Authenticatable
{
    protected $table = 'users';

    protected $guarded = [];
}
