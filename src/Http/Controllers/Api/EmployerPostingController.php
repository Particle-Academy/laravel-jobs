<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;
use ParticleAcademy\LaravelJobs\Enums\EmploymentType;
use ParticleAcademy\LaravelJobs\Http\Resources\JobPostingResource;
use ParticleAcademy\LaravelJobs\Models\JobPosting;
use ParticleAcademy\LaravelJobs\Services\JobPostingService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The employer's own view of its postings — every status, not just visible.
 * Each action is gated by the host's AuthorizesEmployers binding.
 */
class EmployerPostingController extends Controller
{
    public function __construct(
        private readonly JobPostingService $postings,
        private readonly AuthorizesEmployers $authorizer,
    ) {
    }

    public function index(Request $request, string $employer): AnonymousResourceCollection
    {
        $this->assertMayAct($request, $employer);

        $query = JobPosting::query()
            ->forEmployer($employer)
            ->withCount('applications')
            ->latest();

        if (filled($request->input('status'))) {
            $query->where('status', $request->string('status')->toString());
        }

        return JobPostingResource::collection(
            $query->paginate((int) $request->integer('per_page', 25)),
        );
    }

    public function store(Request $request, string $employer): JsonResponse
    {
        $this->assertMayAct($request, $employer);

        $posting = $this->postings->create($employer, $this->validated($request));

        return (new JobPostingResource($posting))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $employer, JobPosting $posting): JobPostingResource
    {
        $this->assertMayAct($request, $employer);
        $this->assertBelongsTo($posting, $employer);

        return new JobPostingResource($posting->load('employer')->loadCount('applications'));
    }

    public function update(Request $request, string $employer, JobPosting $posting): JobPostingResource
    {
        $this->assertMayAct($request, $employer);
        $this->assertBelongsTo($posting, $employer);

        return new JobPostingResource(
            $this->postings->update($posting, $this->validated($request, partial: true)),
        );
    }

    public function destroy(Request $request, string $employer, JobPosting $posting): JsonResponse
    {
        $this->assertMayAct($request, $employer);
        $this->assertBelongsTo($posting, $employer);

        $posting->delete();

        return response()->json(status: 204);
    }

    public function publish(Request $request, string $employer, JobPosting $posting): JobPostingResource
    {
        $this->assertMayAct($request, $employer);
        $this->assertBelongsTo($posting, $employer);

        return new JobPostingResource($this->postings->publish($posting));
    }

    public function unpublish(Request $request, string $employer, JobPosting $posting): JobPostingResource
    {
        $this->assertMayAct($request, $employer);
        $this->assertBelongsTo($posting, $employer);

        return new JobPostingResource($this->postings->unpublish($posting));
    }

    public function close(Request $request, string $employer, JobPosting $posting): JobPostingResource
    {
        $this->assertMayAct($request, $employer);
        $this->assertBelongsTo($posting, $employer);

        return new JobPostingResource($this->postings->close($posting));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title'           => [$required, 'string', 'max:200'],
            'description'     => ['nullable', 'string'],
            'requirements'    => ['nullable', 'string'],
            'employment_type' => ['nullable', 'string', 'in:'.implode(',', EmploymentType::values())],
            'location'        => ['nullable', 'string', 'max:200'],
            'is_remote'       => ['nullable', 'boolean'],
            'pay_min'         => ['nullable', 'integer', 'min:0'],
            'pay_max'         => ['nullable', 'integer', 'min:0', 'gte:pay_min'],
            'pay_unit'        => ['nullable', 'string', 'in:hour,day,week,month,year'],
            'currency'        => ['nullable', 'string', 'size:3'],
            'contact_email'   => ['nullable', 'email', 'max:200'],
            'contact_phone'   => ['nullable', 'string', 'max:50'],
            'apply_url'       => ['nullable', 'url', 'max:500'],
            'openings'        => ['nullable', 'integer', 'min:1'],
            'expires_at'      => ['nullable', 'date'],
        ]);
    }

    private function assertMayAct(Request $request, int|string $employerId): void
    {
        if (! $this->authorizer->allows($request, $employerId)) {
            throw new AccessDeniedHttpException('You may not act for this employer.');
        }
    }

    /** A posting from another employer must look absent, not forbidden. */
    private function assertBelongsTo(JobPosting $posting, int|string $employerId): void
    {
        if ((string) $posting->employer_id !== (string) $employerId) {
            throw new NotFoundHttpException('Job posting not found.');
        }
    }
}
