<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'eyebrow' => $this->eyebrow,
            'desktop_image' => Media::url($this->desktop_image_path),
            'mobile_image' => Media::url($this->mobile_image_path ?: $this->desktop_image_path),
            'cta_label' => $this->cta_label,
            'cta_url' => $this->cta_url,
            'placement' => $this->placement,
            'theme' => $this->theme,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'is_active' => (bool) $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
