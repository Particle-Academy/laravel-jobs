<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Stands in for whatever the host calls its employer — an agency, a company,
 * a studio. Deliberately minimal: id, name, and a moderation status.
 */
class TestEmployer extends Model
{
    protected $table = 'employers';

    protected $guarded = [];
}
