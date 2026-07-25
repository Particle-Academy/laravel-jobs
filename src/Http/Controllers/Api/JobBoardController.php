<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelJobs\Http\Resources\JobPostingResource;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The public board. Only ever exposes visible postings — a draft, closed or
 * expired posting is a 404 here regardless of who asks.
 */
class JobBoardController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search'          => ['nullable', 'string', 'max:200'],
            'employment_type' => ['nullable', 'string'],
            'location'        => ['nullable', 'string', 'max:200'],
            'is_remote'       => ['nullable', 'boolean'],
            'employer_id'     => ['nullable'],
            'per_page'        => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = JobPosting::query()
            ->visible()
            ->with('employer')
            ->search($filters['search'] ?? null)
            ->latest('published_at');

        if (filled($filters['employment_type'] ?? null)) {
            $query->where('employment_type', $filters['employment_type']);
        }

        if (filled($filters['location'] ?? null)) {
            $query->where('location', 'like', '%'.$filters['location'].'%');
        }

        if (array_key_exists('is_remote', $filters) && $filters['is_remote'] !== null) {
            $query->where('is_remote', (bool) $filters['is_remote']);
        }

        if (filled($filters['employer_id'] ?? null)) {
            $query->where('employer_id', $filters['employer_id']);
        }

        $perPage = (int) ($filters['per_page'] ?? config('laravel-jobs.defaults.per_page', 20));

        return JobPostingResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function show(string $slug): JobPostingResource
    {
        $posting = JobPosting::query()
            ->visible()
            ->with('employer')
            ->where('slug', $slug)
            ->first();

        if ($posting === null) {
            throw new NotFoundHttpException('Job posting not found.');
        }

        return new JobPostingResource($posting);
    }
}
