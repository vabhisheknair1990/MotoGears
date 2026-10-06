<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\BannerResource;
use App\Models\Banner;
use App\Services\AuditLogger;
use App\Services\ImageUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

class BannerController extends Controller
{
    public function __construct(private ImageUploadService $uploads, private AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['placement' => ['nullable', Rule::in(['hero', 'promo', 'offer'])]]);
        $page = Banner::when($request->placement, fn ($q, $p) => $q->where('placement', $p))
            ->orderBy('placement')->orderBy('sort_order')->paginate($this->perPage(50));

        return $this->paginated($page, BannerResource::class, 'Banners retrieved successfully');
    }

    public function show(Banner $banner): JsonResponse
    {
        return $this->ok(new BannerResource($banner), 'Banner retrieved successfully');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request, null);
        $banner = Banner::create($this->withImages($request, $data, null));
        $this->audit->log('banner.created', $banner, null, $data);

        return $this->created(new BannerResource($banner), 'Banner created successfully');
    }

    public function update(Request $request, Banner $banner): JsonResponse
    {
        $data = $this->validated($request, $banner);
        $banner->update($this->withImages($request, $data, $banner));
        $this->audit->changes('banner.updated', $banner);

        return $this->ok(new BannerResource($banner->fresh()), 'Banner updated successfully');
    }

    public function destroy(Banner $banner): JsonResponse
    {
        $this->uploads->delete($banner->desktop_image_path);
        $this->uploads->delete($banner->mobile_image_path);
        $banner->delete();
        $this->audit->log('banner.deleted', $banner);

        return $this->deleted('Banner deleted');
    }

    private function validated(Request $request, ?Banner $banner): array
    {
        foreach (['is_active'] as $b) {
            if ($request->has($b)) {
                $request->merge([$b => filter_var($request->input($b), FILTER_VALIDATE_BOOLEAN)]);
            }
        }
        foreach (['starts_at', 'ends_at', 'subtitle', 'eyebrow', 'cta_label', 'cta_url'] as $n) {
            if (in_array($request->input($n), ['', 'null'], true)) {
                $request->merge([$n => null]);
            }
        }
        $req = $banner ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$req, 'string', 'max:190'],
            'subtitle' => ['nullable', 'string', 'max:255'],
            'eyebrow' => ['nullable', 'string', 'max:80'],
            'cta_label' => ['nullable', 'string', 'max:60'],
            'cta_url' => ['nullable', 'string', 'max:255', 'regex:/^(\/|https?:\/\/)/'],
            'placement' => ['sometimes', Rule::in(['hero', 'promo', 'offer'])],
            'theme' => ['sometimes', Rule::in(['dark', 'light'])],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'desktop_image' => ImageUploadService::rules(! $banner, 8192),
            'mobile_image' => ImageUploadService::rules(false, 8192),
        ], ['cta_url.regex' => 'The link must start with / or http(s)://']);
    }

    private function withImages(Request $request, array $data, ?Banner $banner): array
    {
        foreach (['desktop_image' => 'desktop_image_path', 'mobile_image' => 'mobile_image_path'] as $field => $col) {
            if ($request->hasFile($field)) {
                if ($banner) {
                    $this->uploads->delete($banner->{$col});
                }
                $data[$col] = $this->uploads->store($request->file($field), 'banners');
            }
        }

        return Arr::except($data, ['desktop_image', 'mobile_image']);
    }
}
