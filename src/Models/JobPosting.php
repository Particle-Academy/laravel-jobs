<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use ParticleAcademy\LaravelJobs\Database\Factories\JobPostingFactory;
use ParticleAcademy\LaravelJobs\Enums\EmploymentType;
use ParticleAcademy\LaravelJobs\Enums\JobPostingStatus;

/**
 * @property int $id
 * @property int $employer_id
 * @property string $title
 * @property string $slug
 * @property JobPostingStatus $status
 */
class JobPosting extends Model
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'          => JobPostingStatus::class,
            'employment_type' => EmploymentType::class,
            'is_remote'       => 'boolean',
            'pay_min'         => 'integer',
            'pay_max'         => 'integer',
            'openings'        => 'integer',
            'published_at'    => 'datetime',
            'closed_at'       => 'datetime',
            'expires_at'      => 'datetime',
        ];
    }

    protected static function newFactory(): JobPostingFactory
    {
        return JobPostingFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (self $posting): void {
            if (blank($posting->slug)) {
                $posting->slug = self::generateSlug((string) $posting->title);
            }
        });
    }

    /** Slugs are unique, so disambiguate rather than collide. */
    public static function generateSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'job';
        $slug = $base;
        $n = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    public function employer(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('laravel-jobs.employer_model');

        return $this->belongsTo($model, 'employer_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    /** Visible on the public board: published, and not expired. */
    public function scopeVisible(Builder $query): Builder
    {
        $query->where('status', JobPostingStatus::Published->value);

        if ((bool) config('laravel-jobs.defaults.auto_expire', true)) {
            $query->where(function (Builder $q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        }

        return $query;
    }

    public function scopeForEmployer(Builder $query, int|string $employerId): Builder
    {
        return $query->where('employer_id', $employerId);
    }

    /** Free-text search across the fields a candidate would scan. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->where('title', 'like', $like)
                ->orWhere('description', 'like', $like)
                ->orWhere('location', 'like', $like);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | State
    |--------------------------------------------------------------------------
    */

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isVisible(): bool
    {
        return $this->status->isPublic()
            && ! ((bool) config('laravel-jobs.defaults.auto_expire', true) && $this->isExpired());
    }

    public function acceptsApplications(): bool
    {
        return $this->status->acceptsApplications() && ! $this->isExpired();
    }
}
