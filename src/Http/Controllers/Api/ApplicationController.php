<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelJobs\Http\Resources\JobApplicationResource;
use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Services\ApplicationService;
use ParticleAcademy\LaravelJobs\Support\CandidateResolver;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The candidate's side: apply, list your own applications, withdraw.
 * Ownership is by user_id, so no host binding is needed here.
 */
class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly CandidateResolver $candidates,
    ) {
    }

    public function store(Request $request, string $slug): JsonResponse
    {
        $posting = JobPosting::query()->visible()->where('slug', $slug)->first();

        if ($posting === null) {
            throw new NotFoundHttpException('Job posting not found.');
        }

        $data = $request->validate([
            'cover_letter'  => ['nullable', 'string', 'max:5000'],
            'resume_path'   => ['nullable', 'string', 'max:500'],
            'contact_email' => ['nullable', 'email', 'max:200'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $application = $this->applications->submit(
            $posting,
            $this->candidates->resolve($request),
            $data,
        );

        return (new JobApplicationResource($application))->response()->setStatusCode(201);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $applications = JobApplication::query()
            ->forCandidate($this->candidates->resolve($request))
            ->with(['jobPosting' => fn ($q) => $q->with('employer')])
            ->latest()
            ->paginate((int) $request->integer('per_page', 25));

        return JobApplicationResource::collection($applications);
    }

    public function withdraw(Request $request, JobApplication $application): JobApplicationResource
    {
        $candidateId = $this->candidates->resolve($request);

        // Someone else's application is not theirs to see, let alone withdraw.
        if ((string) $application->user_id !== (string) $candidateId) {
            throw new NotFoundHttpException('Application not found.');
        }

        return new JobApplicationResource($this->applications->withdraw($application));
    }
}
