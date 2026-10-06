<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * For small CMS tables (pages, FAQs, testimonials, contact messages, newsletter subscribers,
 * blog categories/tags) whose columns are all safe to expose. Hidden/internal columns are
 * removed via the model's $hidden.
 */
class SimpleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->resource->attributesToArray();
        foreach (['created_at', 'updated_at'] as $k) {
            if (isset($this->resource->{$k})) {
                $data[$k] = $this->resource->{$k}->toIso8601String();
            }
        }

        return $data;
    }
}
