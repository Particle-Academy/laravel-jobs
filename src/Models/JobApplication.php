<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use ParticleAcademy\LaravelJobs\Database\Factories\JobApplicationFactory;
use ParticleAcademy\LaravelJobs\Enums\ApplicationStatus;

/**
 * @property int $id
 * @property int $job_posting_id
 * @property int $user_id
 * @property ApplicationStatus $status
 */
class JobApplication extends Model
{
    /** @use HasFactory<JobApplicationFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status'            => ApplicationStatus::class,
            'submitted_at'      => 'datetime',
            'status_changed_at' => 'datetime',
        ];
    }

    protected static function newFactory(): JobApplicationFactory
    {
        return JobApplicationFactory::new();
    }

    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    public function candidate(): BelongsTo
    {
        /** @var class-string<Model> $model */
        $model = config('laravel-jobs.user_model');

        return $this->belongsTo($model, 'user_id');
    }

    public function scopeForCandidate(Builder $query, int|string $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Applications for every posting belonging to one employer. */
    public function scopeForEmployer(Builder $query, int|string $employerId): Builder
    {
        return $query->whereHas(
            'jobPosting',
            fn (Builder $q) => $q->where('employer_id', $employerId),
        );
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            ApplicationStatus::Rejected->value,
            ApplicationStatus::Hired->value,
            ApplicationStatus::Withdrawn->value,
        ]);
    }
}
