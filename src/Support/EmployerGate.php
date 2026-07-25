<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Decides whether an employer is cleared to advertise.
 *
 * Hosts moderate employers in their own way, so rather than owning a status
 * column this reads the one the host names in config. Drafting is always
 * allowed; the gate only applies to making a posting public.
 */
class EmployerGate
{
    /** Resolve the configured employer model and find one by id. */
    public function find(int|string $employerId): ?Model
    {
        /** @var class-string<Model> $model */
        $model = config('laravel-jobs.employer_model');

        if (! is_string($model) || ! class_exists($model)) {
            return null;
        }

        return $model::query()->find($employerId);
    }

    /**
     * May this employer publish?
     *
     * With no `column` configured, gating is off and everyone may publish.
     * An employer whose model or row cannot be found may not.
     */
    public function allowsPublishing(int|string|Model|null $employer): bool
    {
        $column = config('laravel-jobs.employer_gate.column');

        if ($column === null || $column === '') {
            return true;
        }

        $model = $employer instanceof Model ? $employer : ($employer === null ? null : $this->find($employer));

        if ($model === null) {
            return false;
        }

        // A model that simply does not carry the column is treated as ungated
        // rather than blocked — the host configured a column its employer does
        // not have, and silently refusing every publish would be baffling.
        if (! array_key_exists($column, $model->getAttributes())) {
            return true;
        }

        $approved = config('laravel-jobs.employer_gate.approved', 'approved');

        return $model->getAttribute($column) === $approved;
    }

    /** Human-readable reason, for surfacing in an API error. */
    public function reason(): string
    {
        return 'This employer is not approved to publish job postings yet.';
    }
}
