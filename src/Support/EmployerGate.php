<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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

        $approved = config('laravel-jobs.employer_gate.approved', 'approved');

        // `getAttributes()` is WHAT WAS LOADED on this instance, not what the
        // table has. Treating an absent attribute as "ungated" therefore meant an
        // ordinary `select()` — narrowing a query for performance, anywhere in a
        // host's code — silently switched moderation OFF (laravel-jobs#6).
        //
        // That was the one failure shape this package must not have. Every other
        // sharp edge here is LOUD: forget a binding and the portal is dead, and
        // you know in seconds. This one failed OPEN and looked like success — an
        // unapproved employer's posting went live and nothing logged, threw, or
        // left a PublishDecision to inspect. It also contradicted the rule the
        // README states outright, that removing a host binding switches a feature
        // off rather than opening it up.
        //
        // So the two cases are now told apart, which is what the original comment
        // was reaching for:
        //
        //   - THIS INSTANCE did not load a column the table has -> read it. One
        //     query, only in the case that used to be silently wrong.
        //   - THE TABLE has no such column -> the host named a column its
        //     employer does not have. Still forgiving, because refusing every
        //     publish would be baffling rather than informative — but it warns
        //     now, so a misconfiguration is not silent either.
        if (! array_key_exists($column, $model->getAttributes())) {
            if (! $this->tableHasColumn($model, $column)) {
                $this->warnColumnAbsent($model, $column);

                return true;
            }

            // The column exists and this instance just did not load it. A row
            // that is gone, or hidden by a scope, authorises nothing.
            return $this->gateValueFromDatabase($model, $column) === $approved;
        }

        return $model->getAttribute($column) === $approved;
    }

    /** Human-readable reason, for surfacing in an API error. */
    public function reason(): string
    {
        return 'This employer is not approved to publish job postings yet.';
    }

    /** @var array<string,true> Columns already warned about, so a loop does not flood the log. */
    private static array $warned = [];

    /**
     * Does the employer's table actually have this column?
     *
     * An EXPLICIT schema check, and it has to be: the obvious alternative —
     * reading the column and catching the error — does not work. Measured on
     * SQLite, `->value('no_such_column')` returns `null` **without throwing**, so
     * a missing column and a null value are indistinguishable that way. Relying
     * on the exception would have reintroduced the bug on exactly the driver the
     * test suite runs, and reintroduced it silently.
     *
     * It also must not be inferred from `null`: `null` is a real value a gate
     * column can hold — an employer whose status was never set — and it means NOT
     * approved. Conflating the two would bring this back in a new shape, with a
     * nullable status column treating every unset employer as ungated.
     *
     * If the schema cannot be inspected at all, assume the column EXISTS. That
     * routes to a value read, which yields `null`, which denies — a gate that
     * cannot verify itself must fail closed.
     */
    private function tableHasColumn(Model $model, string $column): bool
    {
        try {
            return Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), $column);
        } catch (\Throwable) {
            return true;
        }
    }

    /**
     * Read the gate column for this model straight from the database.
     *
     * Uses `newQuery()`, so global scopes apply: a soft-deleted employer is not
     * found and therefore not approved. That matches `find()` above, and failing
     * closed is the right direction for a gate.
     */
    private function gateValueFromDatabase(Model $model, string $column): mixed
    {
        if ($model->getKey() === null) {
            // An unsaved model has no row to consult, so there is nothing that
            // could authorise a publish.
            return null;
        }

        return $model->newQuery()->whereKey($model->getKey())->value($column);
    }

    private function warnColumnAbsent(Model $model, string $column): void
    {
        $key = $model->getTable().'.'.$column;

        if (isset(self::$warned[$key])) {
            return;
        }

        self::$warned[$key] = true;

        Log::warning(
            "[laravel-jobs] employer_gate.column is \"{$column}\", which does not exist on "
            ."\"{$model->getTable()}\". Publishing is NOT gated. Set employer_gate.column to a real "
            .'column, or to null to turn gating off deliberately.'
        );
    }
}
