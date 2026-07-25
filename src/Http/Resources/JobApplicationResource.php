<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ParticleAcademy\LaravelJobs\Models\JobApplication;

/**
 * @mixin JobApplication
 */
class JobApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'job_posting_id' => $this->job_posting_id,
            'user_id'        => $this->user_id,

            'cover_letter'   => $this->cover_letter,
            'resume_path'    => $this->resume_path,
            'contact_email'  => $this->contact_email,
            'contact_phone'  => $this->contact_phone,

            'status'         => $this->status->value,
            'status_label'   => $this->status->label(),
            'is_terminal'    => $this->status->isTerminal(),
            'employer_notes' => $this->employer_notes,

            'submitted_at'      => $this->submitted_at?->toIso8601String(),
            'status_changed_at' => $this->status_changed_at?->toIso8601String(),

            'job_posting' => new JobPostingResource($this->whenLoaded('jobPosting')),

            'candidate' => $this->whenLoaded('candidate', fn () => [
                'id'    => $this->candidate?->getKey(),
                'name'  => $this->candidate?->getAttribute('name'),
                'email' => $this->candidate?->getAttribute('email'),
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
