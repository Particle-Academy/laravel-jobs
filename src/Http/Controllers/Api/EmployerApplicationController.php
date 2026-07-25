<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use ParticleAcademy\LaravelJobs\Contracts\AuthorizesEmployers;
use ParticleAcademy\LaravelJobs\Enums\ApplicationStatus;
use ParticleAcademy\LaravelJobs\Http\Resources\JobApplicationResource;
use ParticleAcademy\LaravelJobs\Models\JobApplication;
use ParticleAcademy\LaravelJobs\Services\ApplicationService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The employer's view of who applied, and the ability to move them along.
 */
class EmployerApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly AuthorizesEmployers $authorizer,
    ) {
    }

    public function index(Request $request, string $employer): AnonymousResourceCollection
    {
        $this->assertMayAct($request, $employer);

        $query = JobApplication::query()
            ->forEmployer($employer)
            ->with(['candidate', 'jobPosting'])
            ->latest('submitted_at');

        if (filled($request->input('status'))) {
            $query->where('status', $request->string('status')->toString());
        }

        if (filled($request->input('job_posting_id'))) {
            $query->where('job_posting_id', $request->integer('job_posting_id'));
        }

        return JobApplicationResource::collection(
            $query->paginate((int) $request->integer('per_page', 25)),
        );
    }

    public function updateStatus(Request $request, string $employer, JobApplication $application): JobApplicationResource
    {
        $this->assertMayAct($request, $employer);
        $this->assertBelongsTo($application, $employer);

        $assignable = array_map(
            static fn (ApplicationStatus $s) => $s->value,
            ApplicationStatus::employerAssignable(),
        );

        $data = $request->validate([
            // Withdrawn is the candidate's to set, so it is absent from this list.
            'status'         => ['required', 'string', 'in:'.implode(',', $assignable)],
            'employer_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        return new JobApplicationResource(
            $this->applications->changeStatus(
                $application,
                ApplicationStatus::from($data['status']),
                $data['employer_notes'] ?? null,
            ),
        );
    }

    private function assertMayAct(Request $request, int|string $employerId): void
    {
        if (! $this->authorizer->allows($request, $employerId)) {
            throw new AccessDeniedHttpException('You may not act for this employer.');
        }
    }

    private function assertBelongsTo(JobApplication $application, int|string $employerId): void
    {
        $ownerId = $application->jobPosting?->employer_id;

        if ((string) $ownerId !== (string) $employerId) {
            throw new NotFoundHttpException('Application not found.');
        }
    }
}
