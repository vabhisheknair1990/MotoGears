<?php

namespace App\Http\Resources;

use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Full product detail for the product page. */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $card = (new ProductCardResource($this->resource))->toArray($request);

        return array_merge($card, [
            'description' => $this->description,
            'tax_rate' => (float) $this->tax_rate,
            'position' => $this->position,
            'material' => $this->material,
            'dimensions' => $this->dimensions,
            'weight_kg' => $this->weight_kg !== null ? (float) $this->weight_kg : null,
            'warranty' => $this->warranty,
            'installation_info' => $this->installation_info,
            'whats_included' => $this->whats_included ?? [],
            'specifications' => $this->specifications ?? [],
            'tags' => $this->tags ?? [],
            'video_url' => $this->video_url,
            'meta_title' => $this->meta_title ?: $this->name,
            'meta_description' => $this->meta_description ?: $this->short_description,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
                'parent' => $this->category->parent ? ['id' => $this->category->parent->id, 'name' => $this->category->parent->name, 'slug' => $this->category->parent->slug] : null,
            ]),
            'brand' => $this->whenLoaded('brand', fn () => [
                'id' => $this->brand->id, 'name' => $this->brand->name, 'slug' => $this->brand->slug,
                'logo' => Media::url($this->brand->logo_path), 'country' => $this->brand->country,
            ]),
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn ($i) => [
                'id' => $i->id, 'url' => Media::url($i->path), 'alt' => $i->alt ?: $this->name, 'is_primary' => $i->is_primary,
            ])->values()),
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->where('is_active', true)->map(fn ($v) => [
                'id' => $v->id, 'sku' => $v->sku, 'name' => $v->name, 'options' => $v->options, 'price' => (float) $this->price + (float) $v->price_adjustment,
            ])->values()),
            'attributes' => $this->whenLoaded('attributeValues', fn () => $this->attributeValues->groupBy(fn ($v) => $v->attribute->name)
                ->map(fn ($vals, $name) => ['name' => $name, 'values' => $vals->pluck('value')->values()])->values()),
            'faqs' => $this->whenLoaded('faqs', fn () => $this->faqs->map(fn ($f) => ['question' => $f->question, 'answer' => $f->answer])->values()),
            'compatibility' => $this->whenLoaded('compatibilities', fn () => CompatibilityResource::collection($this->compatibilities)->resolve($request)),
            'inventory' => $this->whenLoaded('inventory', fn () => [
                'status' => $this->inventory?->status() ?? 'out_of_stock',
                'available' => $this->inventory?->available() ?? 0,
                'allow_backorder' => (bool) $this->inventory?->allow_backorder,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ]);
    }
}
