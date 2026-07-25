<?php

declare(strict_types=1);

namespace ParticleAcademy\LaravelJobs\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use ParticleAcademy\LaravelJobs\Models\JobPosting;

/**
 * @mixin JobPosting
 */
class JobPostingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'employer_id'     => $this->employer_id,
            'title'           => $this->title,
            'slug'            => $this->slug,
            'description'     => $this->description,
            'requirements'    => $this->requirements,

            'employment_type' => $this->employment_type?->value,
            'employment_type_label' => $this->employment_type?->label(),
            'location'        => $this->location,
            'is_remote'       => (bool) $this->is_remote,

            'pay_min'         => $this->pay_min,
            'pay_max'         => $this->pay_max,
            'pay_unit'        => $this->pay_unit,
            'currency'        => $this->currency,

            'contact_email'   => $this->contact_email,
            'contact_phone'   => $this->contact_phone,
            'apply_url'       => $this->apply_url,

            'status'          => $this->status->value,
            'status_label'    => $this->status->label(),
            'published_at'    => $this->published_at?->toIso8601String(),
            'closed_at'       => $this->closed_at?->toIso8601String(),
            'expires_at'      => $this->expires_at?->toIso8601String(),

            'openings'            => $this->openings,
            'applications_count'  => $this->applications_count,
            'is_visible'          => $this->isVisible(),
            'accepts_applications'=> $this->acceptsApplications(),

            'employer' => $this->whenLoaded('employer', fn () => [
                'id'   => $this->employer?->getKey(),
                'name' => $this->employer?->getAttribute('name'),
            ]),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
