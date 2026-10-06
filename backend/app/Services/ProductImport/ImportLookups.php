<?php

namespace App\Services\ProductImport;

use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\VehicleManufacturer;
use App\Models\VehicleModel;
use App\Models\VehicleVariant;
use Illuminate\Support\Str;

/**
 * Turns the human-readable values used in the spreadsheet (category paths, brand names,
 * vehicle labels, "Filter: Value" pairs) into database IDs — and produces the same labels
 * for the template's dropdown lists, so what people pick is always something we can read back.
 * Matching ignores case, extra spaces and the separator style (>, ›, /).
 */
class ImportLookups
{
    public const SEP = ' > ';

    /** @var array<int, string> category id => path */
    public array $categoryPaths = [];
    /** @var array<int, string> */
    public array $categoryTypes = [];
    /** @var array<int, string> brand id => name */
    public array $brandNames = [];
    /** @var list<array{label: string, manufacturer: int, model: ?int, variant: ?int}> */
    public array $vehicles = [];
    /** @var array<int, string> attribute id => name */
    public array $attributeNames = [];
    /** @var array<int, array<string, int>> attribute id => [normalised value => value id] */
    private array $attributeValues = [];
    /** @var array<int, array{label: string, attribute_id: int}> */
    public array $attributeValueLabels = [];

    private array $categoryIndex = [];
    private array $brandIndex = [];
    private array $vehicleIndex = [];
    private array $attributeIndex = [];

    public static function load(): self
    {
        $l = new self;
        $l->loadCategories();
        $l->loadBrands();
        $l->loadVehicles();
        $l->loadAttributes();

        return $l;
    }

    public static function norm(?string $s): string
    {
        $s = mb_strtolower(trim((string) $s));
        $s = str_replace(['›', '»', '→', '/', '\\', '|'], '>', $s);
        $s = str_replace(['–', '—', '−'], '-', $s);
        $s = preg_replace('/\s*>\s*/u', '>', $s);

        return preg_replace('/\s+/u', ' ', $s);
    }

    // ── Categories ────────────────────────────────────────────────
    private function loadCategories(): void
    {
        $cats = Category::query()->get(['id', 'parent_id', 'name', 'slug', 'vehicle_type', 'is_active', 'sort_order']);
        $byId = $cats->keyBy('id');
        $path = function (Category $c) use ($byId): string {
            $parts = [$c->name];
            $seen = [$c->id => true];
            while ($c->parent_id && ($c = $byId->get($c->parent_id)) && ! isset($seen[$c->id])) {
                $seen[$c->id] = true;
                array_unshift($parts, $c->name);
            }

            return implode(self::SEP, $parts);
        };

        $nameCount = $cats->countBy(fn ($c) => self::norm($c->name));
        foreach ($cats->sortBy(fn ($c) => $path($c)) as $c) {
            $p = $path($c);
            $this->categoryPaths[$c->id] = $p;
            $this->categoryTypes[$c->id] = $c->vehicle_type;
            $this->categoryIndex[self::norm($p)] = $c->id;
            $this->categoryIndex['slug:'.$c->slug] = $c->id;
            if (($nameCount[self::norm($c->name)] ?? 0) === 1) {
                $this->categoryIndex[self::norm($c->name)] ??= $c->id;
            }
        }
    }

    public function category(mixed $value): ?int
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        if (ctype_digit($v) && isset($this->categoryPaths[(int) $v])) {
            return (int) $v;
        }

        return $this->categoryIndex[self::norm($v)] ?? $this->categoryIndex['slug:'.Str::slug($v)] ?? null;
    }

    // ── Brands ────────────────────────────────────────────────────
    private function loadBrands(): void
    {
        foreach (Brand::query()->orderBy('name')->get(['id', 'name', 'slug']) as $b) {
            $this->brandNames[$b->id] = $b->name;
            $this->brandIndex[self::norm($b->name)] = $b->id;
            $this->brandIndex['slug:'.$b->slug] = $b->id;
        }
    }

    public function brand(mixed $value): ?int
    {
        $v = trim((string) $value);
        if ($v === '') {
            return null;
        }
        if (ctype_digit($v) && isset($this->brandNames[(int) $v])) {
            return (int) $v;
        }

        return $this->brandIndex[self::norm($v)] ?? $this->brandIndex['slug:'.Str::slug($v)] ?? null;
    }

    // ── Vehicles ──────────────────────────────────────────────────
    public static function makeLabel(string $make, string $suffix = ' (all models)'): string
    {
        return $make.$suffix;
    }

    public static function modelLabel(string $make, string $model): string
    {
        return $make.self::SEP.$model.' (all variants)';
    }

    public static function variantLabel(string $make, string $model, VehicleVariant $v): string
    {
        return $make.self::SEP.$model.self::SEP.$v->name.' ('.$v->year_from.'-'.($v->year_to ?? 'present').')';
    }

    private function loadVehicles(): void
    {
        $makes = VehicleManufacturer::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'slug']);
        $models = VehicleModel::query()->orderBy('name')->get(['id', 'vehicle_manufacturer_id', 'name', 'slug'])->groupBy('vehicle_manufacturer_id');
        $variants = VehicleVariant::query()->orderBy('name')->orderBy('year_from')->get()->groupBy('vehicle_model_id');

        $short = [];
        foreach ($makes as $make) {
            $this->addVehicle(self::makeLabel($make->name), $make->id, null, null);
            $short[] = [self::norm($make->name), $make->id, null, null];
            foreach ($models->get($make->id, collect()) as $model) {
                $this->addVehicle(self::modelLabel($make->name, $model->name), $make->id, $model->id, null);
                $short[] = [self::norm($make->name.self::SEP.$model->name), $make->id, $model->id, null];
                foreach ($variants->get($model->id, collect()) as $variant) {
                    $this->addVehicle(self::variantLabel($make->name, $model->name, $variant), $make->id, $model->id, $variant->id);
                    $short[] = [self::norm($make->name.self::SEP.$model->name.self::SEP.$variant->name), $make->id, $model->id, $variant->id];
                }
            }
        }
        // Also accept labels typed without the "(all models)" / year suffix when they are unambiguous.
        $counts = array_count_values(array_column($short, 0));
        foreach ($short as [$key, $make, $model, $variant]) {
            if ($counts[$key] === 1) {
                $this->vehicleIndex[$key] ??= ['manufacturer' => $make, 'model' => $model, 'variant' => $variant];
            }
        }
    }

    private function addVehicle(string $label, int $make, ?int $model, ?int $variant): void
    {
        $this->vehicles[] = ['label' => $label, 'manufacturer' => $make, 'model' => $model, 'variant' => $variant];
        $this->vehicleIndex[self::norm($label)] = ['manufacturer' => $make, 'model' => $model, 'variant' => $variant];
    }

    /** @return array{manufacturer: int, model: ?int, variant: ?int}|null */
    public function vehicle(mixed $value): ?array
    {
        $v = trim((string) $value);

        return $v === '' ? null : ($this->vehicleIndex[self::norm($v)] ?? null);
    }

    public function vehicleLabelFor(?int $make, ?int $model, ?int $variant): ?string
    {
        foreach ($this->vehicles as $row) {
            if ($row['manufacturer'] === $make && $row['model'] === $model && $row['variant'] === $variant) {
                return $row['label'];
            }
        }

        return null;
    }

    // ── Attributes ("Filter: Value") ──────────────────────────────
    private function loadAttributes(): void
    {
        $attrs = Attribute::query()->with(['values' => fn ($q) => $q->orderBy('sort_order')->orderBy('value')])->orderBy('sort_order')->orderBy('name')->get();
        foreach ($attrs as $a) {
            $this->attributeNames[$a->id] = $a->name;
            $this->attributeIndex[self::norm($a->name)] = $a->id;
            $this->attributeIndex[self::norm($a->slug)] = $a->id;
            foreach ($a->values as $v) {
                $this->attributeValues[$a->id][self::norm($v->value)] = $v->id;
                $this->attributeValues[$a->id][self::norm($v->slug)] ??= $v->id;
                $this->attributeValueLabels[$v->id] = ['label' => $a->name.': '.$v->value, 'attribute_id' => $a->id];
            }
        }
    }

    public function attribute(string $name): ?int
    {
        return $this->attributeIndex[self::norm($name)] ?? null;
    }

    /**
     * Value id for "Filter: Value". Unknown values of a known filter are created (inside the
     * caller's transaction) and only remembered when $remember is true, so a rolled-back check
     * run never leaves stale IDs behind.
     *
     * @return array{0: ?int, 1: bool} [value id, created]
     */
    public function attributeValue(int $attributeId, string $value, bool $remember): array
    {
        $key = self::norm($value);
        if (isset($this->attributeValues[$attributeId][$key])) {
            return [$this->attributeValues[$attributeId][$key], false];
        }
        $slug = Str::slug($value) ?: Str::lower(Str::random(6));
        $existing = AttributeValue::where('attribute_id', $attributeId)->where(fn ($q) => $q->where('slug', $slug)->orWhere('value', $value))->first();
        $created = false;
        if (! $existing) {
            $existing = AttributeValue::create(['attribute_id' => $attributeId, 'value' => trim($value), 'slug' => $slug,
                'sort_order' => (int) AttributeValue::where('attribute_id', $attributeId)->max('sort_order') + 1]);
            $created = true;
        }
        if ($remember) {
            $this->attributeValues[$attributeId][$key] = $existing->id;
            $this->attributeValueLabels[$existing->id] = ['label' => ($this->attributeNames[$attributeId] ?? '').': '.$existing->value, 'attribute_id' => $attributeId];
        }

        return [$existing->id, $created];
    }
}
