<?php

namespace App\Services\ProductImport;

use App\Enums\InventoryTransactionType;
use App\Exceptions\BusinessException;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductImport;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ImageUploadService;
use App\Services\InventoryService;
use App\Services\ProductAdminService;
use App\Support\Media;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Runs an uploaded spreadsheet in one of two passes:
 *  - check  ($dryRun = true):  every product is validated and saved inside a transaction that is
 *                               rolled back, so the result is exactly what a real import would do;
 *  - import ($dryRun = false): the same, committed, plus image downloads and stock adjustments.
 * Each SKU (its Products row + Compatibility/Variants/FAQs rows) is one unit: it is saved
 * completely or not at all, and one bad product never blocks the others.
 */
class ImportProcessor
{
    private ImportLookups $lookups;
    private ProductImport $import;
    private bool $dryRun;
    private ?User $user;

    private array $issues = [];
    private int $errorCount = 0;
    private int $warningCount = 0;
    private array $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];
    private int $imageCount = 0;
    private float $lastFlush = 0;
    private ?bool $unitFailed = null;
    /** sheet|row|column already reported for the current unit (avoids duplicate messages). */
    private array $reported = [];

    private const BOOL_TRUE = ['yes', 'y', 'true', '1', 'active', 'on', 'enabled'];
    private const BOOL_FALSE = ['no', 'n', 'false', '0', 'inactive', 'off', 'disabled'];
    private const NULLABLE_TEXT = ['part_number', 'short_description', 'description', 'position', 'material', 'dimensions', 'warranty',
        'installation_info', 'video_url', 'meta_title', 'meta_description'];
    private const ALWAYS_TEXT = ['name', 'slug'];

    public function __construct(
        private ProductAdminService $products,
        private InventoryService $inventory,
        private ImageUploadService $uploads,
        private RemoteImageFetcher $fetcher,
    ) {}

    public function run(ProductImport $import, bool $dryRun): void
    {
        $this->import = $import;
        $this->dryRun = $dryRun;
        $this->user = $import->user;
        $this->issues = [];
        $this->errorCount = $this->warningCount = $this->imageCount = 0;
        $this->counts = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0];

        $import->forceFill([
            'status' => $dryRun ? ProductImport::VALIDATING : ProductImport::IMPORTING,
            'processed_rows' => 0, 'total_rows' => 0, 'created_count' => 0, 'updated_count' => 0, 'skipped_count' => 0,
            'failed_count' => 0, 'warning_count' => 0, 'error_count' => 0, 'errors' => [], 'message' => null,
            'started_at' => $dryRun ? $import->started_at : now(),
        ])->save();

        $reader = new WorkbookReader;
        $sheets = $reader->read(Storage::disk('local')->path($import->file_path), $import->original_name);
        foreach (array_slice($reader->notices, 0, 20) as $notice) {
            $this->issue('warning', ImportSchema::PRODUCTS, null, null, null, $notice);
        }

        $this->lookups = ImportLookups::load();
        $units = $this->buildUnits($sheets);
        $existing = $this->existingProducts(array_keys($units));

        $preFailed = $this->counts['failed'];   // rows rejected before processing (missing / duplicate SKU)
        $import->forceFill(['total_rows' => count($units) + $preFailed])->save();
        $this->lastFlush = microtime(true);

        $done = $preFailed;
        $cancelled = false;
        foreach ($units as $sku => $unit) {
            $outcome = $this->processUnit((string) $sku, $unit, $existing[(string) $sku] ?? null);
            $this->counts[$outcome]++;
            $done++;
            if ($this->shouldFlush($done, count($units) + $preFailed)) {
                $this->flush($done);
                if (ProductImport::whereKey($import->id)->value('cancel_requested')) {
                    $cancelled = true;
                    break;
                }
            }
        }
        $this->flush($done);
        $this->finish($sheets, $cancelled);
    }

    // ── Units ─────────────────────────────────────────────────────
    /** @return array<string, array{product: ?array, compat: list<array>, variants: list<array>, faqs: list<array>}> */
    private function buildUnits(array $sheets): array
    {
        $units = [];
        $firstRow = [];
        foreach ($sheets[ImportSchema::PRODUCTS] as $row) {
            $sku = $this->sku($row['values']['sku'] ?? null);
            if ($sku === '') {
                $this->issue('error', ImportSchema::PRODUCTS, $row['row'], null, 'sku', 'SKU is required — every product needs a unique code.');
                $this->counts['failed']++;

                continue;
            }
            if (isset($firstRow[$sku])) {
                $this->issue('error', ImportSchema::PRODUCTS, $row['row'], $sku, 'sku', "This SKU is already used on row {$firstRow[$sku]} — each product must appear only once.");
                $this->counts['failed']++;

                continue;
            }
            $firstRow[$sku] = $row['row'];
            $units[$sku] = ['product' => $row, 'compat' => [], 'variants' => [], 'faqs' => []];
        }
        foreach ([ImportSchema::COMPATIBILITY => 'compat', ImportSchema::VARIANTS => 'variants', ImportSchema::FAQS => 'faqs'] as $sheet => $bucket) {
            foreach ($sheets[$sheet] as $row) {
                $sku = $this->sku($row['values']['sku'] ?? null);
                if ($sku === '') {
                    $this->issue('error', $sheet, $row['row'], null, 'sku', 'Product SKU is required so we know which product this row belongs to.');

                    continue;
                }
                $units[$sku] ??= ['product' => null, 'compat' => [], 'variants' => [], 'faqs' => []];
                $units[$sku][$bucket][] = $row;
            }
        }

        return $units;
    }

    /** @return array<string, Product> */
    private function existingProducts(array $skus): array
    {
        $found = [];
        foreach (array_chunk($skus, 500) as $chunk) {
            foreach (Product::withTrashed()->whereIn('sku', $chunk)->get() as $p) {
                $found[strtoupper($p->sku)] = $p;
            }
        }

        return $found;
    }

    /** @return 'created'|'updated'|'skipped'|'failed' */
    private function processUnit(string $sku, array $unit, ?Product $existing): string
    {
        $row = $unit['product'];
        $anchor = $row ? [ImportSchema::PRODUCTS, $row['row']] : $this->firstChildAnchor($unit);
        $this->unitFailed = false;
        $this->reported = [];

        if ($existing?->trashed()) {
            $this->issue('error', $anchor[0], $anchor[1], $sku, 'sku', 'This SKU belongs to a deleted product. Restore it from Products → Deleted first, or use a different SKU.');

            return 'failed';
        }
        if (! $existing && ! $row) {
            foreach ($this->childRows($unit) as [$sheet, $r]) {
                $this->issue('error', $sheet, $r['row'], $sku, 'sku', "No product with SKU {$sku} exists in the Products sheet or in your store.");
            }

            return 'failed';
        }
        if ($existing && $this->import->mode === 'create') {
            $this->issue('warning', $anchor[0], $anchor[1], $sku, 'sku', 'Skipped — this SKU already exists and the import is set to "Add new products only".');

            return 'skipped';
        }
        if (! $existing && $this->import->mode === 'update') {
            $this->issue('warning', $anchor[0], $anchor[1], $sku, 'sku', 'Skipped — this SKU is new and the import is set to "Update existing products only".');

            return 'skipped';
        }

        $creating = $existing === null;
        $locator = [];   // validator key → [sheet, row, column key]
        $stock = $images = null;

        // Everything for this SKU — including new filter values — happens in one transaction:
        // committed on a real import, always rolled back during the check.
        DB::beginTransaction();
        $committed = false;
        try {
            $data = $row ? $this->mapProduct($sku, $row, $existing, $locator) : [];
            $stock = $data['__stock'] ?? null;
            $images = $data['__images'] ?? null;
            unset($data['__stock'], $data['__images']);

            if ($unit['compat']) {
                $data['compatibilities'] = $this->mapCompatibilities($sku, $unit['compat'], $locator);
            }
            if ($unit['variants']) {
                $data['variants'] = $this->mapVariants($sku, $unit['variants'], $existing, $locator);
            }
            if ($unit['faqs']) {
                $data['faqs'] = $this->mapFaqs($sku, $unit['faqs'], $locator);
            }
            if ($creating) {
                $data['sku'] = $sku;
                $data['initial_stock'] = $stock ?? 0;
            }

            $this->validate($sku, $data, $existing, $locator, $anchor);
            if ($existing && $stock !== null) {
                $inv = $existing->inventory;
                if ($inv && $stock < $inv->reserved) {
                    $this->issue('error', ImportSchema::PRODUCTS, $row['row'], $sku, 'stock', "Stock can't be set to {$stock} — {$inv->reserved} unit(s) are reserved for open orders.");
                }
            }
            if ($this->unitFailed) {
                return 'failed';
            }

            if ($creating) {
                $product = $this->products->create($data, $this->user);
            } else {
                $product = $this->products->update($existing, $data, $this->user);
                if ($stock !== null) {
                    $inv = $this->inventory->forProduct($product);
                    if ($stock !== $inv->quantity && ! $this->dryRun) {
                        $this->inventory->adjust($inv, InventoryTransactionType::ManualAdjustment, $stock - $inv->quantity,
                            'Set by product import #'.$this->import->id, 'Import #'.$this->import->id, $this->user);
                    }
                }
            }
            if (! $this->dryRun) {
                DB::commit();
                $committed = true;
            }
        } catch (BusinessException $e) {
            $this->issue('error', $anchor[0], $anchor[1], $sku, null, $e->getMessage());

            return 'failed';
        } catch (\Throwable $e) {
            Log::warning('Product import row failed', ['import' => $this->import->id, 'sku' => $sku, 'error' => $e->getMessage()]);
            $this->issue('error', $anchor[0], $anchor[1], $sku, null, 'Could not be saved: '.Str::limit($e->getMessage(), 180));

            return 'failed';
        } finally {
            if (! $committed) {
                DB::rollBack();
            }
        }

        if ($images !== null) {
            $this->imageCount += count($images);
            if (! $this->dryRun) {
                $this->syncImages($product, $images, $row['row']);
            }
        }

        return $creating ? 'created' : 'updated';
    }

    // ── Products row ──────────────────────────────────────────────
    private function mapProduct(string $sku, array $row, ?Product $existing, array &$locator): array
    {
        $v = $row['values'];
        $r = $row['row'];
        $creating = $existing === null;
        $data = [];
        $err = fn (string $col, string $msg) => $this->issue('error', ImportSchema::PRODUCTS, $r, $sku, $col, $msg);
        $isClear = fn ($x) => is_string($x) && strtoupper(trim($x)) === ImportSchema::CLEAR;
        $has = fn (string $k) => array_key_exists($k, $v) && $v[$k] !== null && $v[$k] !== '';

        foreach ([...self::ALWAYS_TEXT, ...self::NULLABLE_TEXT] as $k) {
            if (! $has($k)) {
                continue;
            }
            if ($isClear($v[$k])) {
                in_array($k, self::NULLABLE_TEXT, true) ? $data[$k] = null : $err($k, ImportSchema::header(ImportSchema::PRODUCTS, $k).' cannot be cleared.');

                continue;
            }
            $data[$k] = $k === 'description' || $k === 'installation_info' ? (string) $v[$k] : trim(preg_replace('/\s+/u', ' ', (string) $v[$k]));
        }
        if (isset($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }
        if ($creating && ! isset($data['meta_title']) && isset($data['name'])) {
            $data['meta_title'] = $data['name'];
        }

        if ($has('category')) {
            $id = $this->lookups->category($v['category']);
            $id ? $data['category_id'] = $id : $err('category', 'Category "'.$v['category'].'" was not found — pick one from the dropdown (all categories are on the Lists sheet).');
        }
        if ($has('brand')) {
            $id = $this->lookups->brand($v['brand']);
            $id ? $data['brand_id'] = $id : $err('brand', 'Brand "'.$v['brand'].'" was not found — pick one from the dropdown or add the brand first (Catalog → Brands).');
        }
        if ($has('vehicle_type')) {
            $type = match (strtolower(trim((string) $v['vehicle_type']))) {
                'car', 'cars', 'four wheeler', '4w' => 'car',
                'motorcycle', 'motorcycles', 'bike', 'bikes', 'two wheeler', '2w', 'scooter' => 'motorcycle',
                'universal', 'all', 'both' => 'universal',
                default => null,
            };
            $type ? $data['vehicle_type'] = $type : $err('vehicle_type', 'Vehicle type must be Car, Motorcycle or Universal.');
        } elseif ($creating) {
            // Default to the category's type. If the category itself was not recognised, that error is
            // already reported — use a placeholder so it isn't followed by a confusing "Vehicle type required".
            $catType = isset($data['category_id']) ? ($this->lookups->categoryTypes[$data['category_id']] ?? 'car') : 'car';
            $data['vehicle_type'] = in_array($catType, ['car', 'motorcycle', 'universal'], true) ? $catType : 'car';
        }

        foreach (['is_universal', 'is_active', 'is_featured', 'allow_backorder'] as $k) {
            if ($has($k)) {
                $b = $this->bool($v[$k]);
                $b === null ? $err($k, ImportSchema::header(ImportSchema::PRODUCTS, $k).' must be Yes or No.') : $data[$k] = $b;
            }
        }
        foreach (['mrp', 'price', 'cost_price', 'weight_kg'] as $k) {
            if ($has($k)) {
                if ($isClear($v[$k]) && in_array($k, ['cost_price', 'weight_kg'], true)) {
                    $data[$k] = $k === 'cost_price' ? 0 : null;

                    continue;
                }
                $n = $this->number($v[$k]);
                $n === null ? $err($k, ImportSchema::header(ImportSchema::PRODUCTS, $k).' must be a number, e.g. 1499 or 1499.50 (no ₹ sign).') : $data[$k] = $n;
            }
        }
        if ($has('tax_rate')) {
            $n = $this->number($v['tax_rate']);
            $n !== null && in_array($n, [0.0, 5.0, 12.0, 18.0, 28.0], true) ? $data['tax_rate'] = (int) $n : $err('tax_rate', 'GST % must be 0, 5, 12, 18 or 28.');
        }
        foreach (['stock', 'low_stock_threshold'] as $k) {
            if ($has($k)) {
                $n = $this->number($v[$k]);
                if ($n === null || $n < 0 || floor($n) !== $n) {
                    $err($k, ImportSchema::header(ImportSchema::PRODUCTS, $k).' must be a whole number of 0 or more.');
                } elseif ($k === 'stock') {
                    $data['__stock'] = (int) $n;
                } else {
                    $data[$k] = (int) $n;
                }
            }
        }

        if ($has('whats_included')) {
            $data['whats_included'] = $isClear($v['whats_included']) ? [] : $this->split((string) $v['whats_included'], '/\s*[|\n]\s*/u');
        }
        if ($has('tags')) {
            $data['tags'] = $isClear($v['tags']) ? [] : array_values(array_unique(array_map('mb_strtolower', $this->split((string) $v['tags'], '/\s*[,|\n]\s*/u'))));
        }
        if ($has('specifications')) {
            if ($isClear($v['specifications'])) {
                $data['specifications'] = [];
            } else {
                $specs = [];
                foreach ($this->split((string) $v['specifications'], '/\s*[|\n]\s*/u') as $pair) {
                    [$label, $value] = array_map('trim', array_pad(explode(':', $pair, 2), 2, ''));
                    if ($label === '' || $value === '') {
                        $err('specifications', "\"{$pair}\" should be written as Label: Value (for example Thickness: 17 mm).");

                        continue;
                    }
                    $specs[] = ['label' => $label, 'value' => $value];
                }
                $data['specifications'] = $specs;
            }
        }
        if ($has('attributes')) {
            $data['attribute_value_ids'] = $isClear($v['attributes']) ? [] : $this->attributeIds($sku, $r, (string) $v['attributes']);
        }
        if ($has('image_urls')) {
            if ($isClear($v['image_urls'])) {
                $data['__images'] = [];
            } else {
                $urls = array_values(array_unique($this->split((string) $v['image_urls'], '/\s*[|\n]\s*|\s+(?=https?:\/\/)/u')));
                foreach ($urls as $i => $url) {
                    if (! filter_var($url, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $url)) {
                        $err('image_urls', 'Image '.($i + 1).' is not a valid web link (it must start with http:// or https://).');
                    }
                }
                if (count($urls) > ImportSchema::MAX_IMAGES) {
                    $err('image_urls', 'A product can have at most '.ImportSchema::MAX_IMAGES.' images ('.count($urls).' given).');
                }
                $data['__images'] = $urls;
            }
        }

        if ($creating) {
            foreach (['name', 'category', 'brand', 'mrp', 'price'] as $k) {
                if (! $has($k)) {
                    $err($k, ImportSchema::header(ImportSchema::PRODUCTS, $k).' is required for a new product.');
                }
            }
        }

        foreach (array_keys(ImportSchema::columns(ImportSchema::PRODUCTS)) as $k) {
            $target = match ($k) { 'category' => 'category_id', 'brand' => 'brand_id', 'attributes' => 'attribute_value_ids', 'stock' => 'initial_stock', default => $k };
            $locator[$target] = [ImportSchema::PRODUCTS, $r, $k];
        }

        return $data;
    }

    private function attributeIds(string $sku, int $r, string $text): array
    {
        $ids = [];
        foreach ($this->split($text, '/\s*[|\n;]\s*/u') as $pair) {
            [$name, $value] = array_map('trim', array_pad(explode(':', $pair, 2), 2, ''));
            if ($name === '' || $value === '') {
                $this->issue('error', ImportSchema::PRODUCTS, $r, $sku, 'attributes', "\"{$pair}\" should be written as Filter: Value (for example Position: Front).");

                continue;
            }
            $attributeId = $this->lookups->attribute($name);
            if (! $attributeId) {
                $this->issue('error', ImportSchema::PRODUCTS, $r, $sku, 'attributes', "Filter \"{$name}\" does not exist — create it under Catalog → Attributes, or use one listed on the Lists sheet.");

                continue;
            }
            [$id, $created] = $this->lookups->attributeValue($attributeId, $value, false);
            if ($created) {
                $this->issue('warning', ImportSchema::PRODUCTS, $r, $sku, 'attributes', ($this->dryRun ? 'Will add' : 'Added').' the new value "'.$value.'" to the '.$this->lookups->attributeNames[$attributeId].' filter.');
            }
            $ids[] = $id;
        }

        return array_values(array_unique($ids));
    }

    // ── Related sheets ────────────────────────────────────────────
    private function mapCompatibilities(string $sku, array $rows, array &$locator): array
    {
        $out = [];
        foreach ($rows as $row) {
            $v = $row['values'];
            $r = $row['row'];
            $err = fn (string $col, string $msg) => $this->issue('error', ImportSchema::COMPATIBILITY, $r, $sku, $col, $msg);
            $vehicle = $this->lookups->vehicle($v['vehicle'] ?? null);
            if (! $vehicle) {
                empty($v['vehicle'])
                    ? $err('vehicle', 'Vehicle is required — pick a make, model or variant from the dropdown.')
                    : $err('vehicle', 'Vehicle "'.$v['vehicle'].'" was not found — pick it from the dropdown (all vehicles are on the Lists sheet).');

                continue;
            }
            $years = [];
            foreach (['year_from', 'year_to'] as $k) {
                $years[$k] = null;
                if (($v[$k] ?? null) !== null && $v[$k] !== '') {
                    $n = $this->number($v[$k]);
                    if ($n === null || floor($n) !== $n || $n < 1950 || $n > 2100) {
                        $err($k, ImportSchema::header(ImportSchema::COMPATIBILITY, $k).' must be a year between 1950 and 2100.');
                    } else {
                        $years[$k] = (int) $n;
                    }
                }
            }
            if ($years['year_from'] && $years['year_to'] && $years['year_from'] > $years['year_to']) {
                $err('year_to', '"Year to" cannot be earlier than "Year from".');
            }
            $i = count($out);
            $out[] = [
                'vehicle_manufacturer_id' => $vehicle['manufacturer'],
                'vehicle_model_id' => $vehicle['model'],
                'vehicle_variant_id' => $vehicle['variant'],
                'year_from' => $years['year_from'],
                'year_to' => $years['year_to'],
                'notes' => isset($v['notes']) ? (string) $v['notes'] : null,
            ];
            foreach (['vehicle_manufacturer_id' => 'vehicle', 'vehicle_model_id' => 'vehicle', 'vehicle_variant_id' => 'vehicle', 'year_from' => 'year_from', 'year_to' => 'year_to', 'notes' => 'notes'] as $field => $col) {
                $locator["compatibilities.{$i}.{$field}"] = [ImportSchema::COMPATIBILITY, $r, $col];
            }
        }

        return $out;
    }

    private function mapVariants(string $sku, array $rows, ?Product $existing, array &$locator): array
    {
        $out = [];
        $seen = [];
        $current = $existing ? $existing->variants()->get()->keyBy(fn ($v) => strtoupper($v->sku)) : collect();
        foreach ($rows as $row) {
            $v = $row['values'];
            $r = $row['row'];
            $err = fn (string $col, string $msg) => $this->issue('error', ImportSchema::VARIANTS, $r, $sku, $col, $msg);
            $vsku = $this->sku($v['variant_sku'] ?? null);
            if ($vsku === '') {
                $err('variant_sku', 'Variant SKU is required.');

                continue;
            }
            if (! preg_match('/^[A-Z0-9\-_]+$/', $vsku)) {
                $err('variant_sku', 'Variant SKU may only contain letters, numbers, dashes and underscores.');
            }
            if (isset($seen[$vsku])) {
                $err('variant_sku', "Variant SKU {$vsku} is listed twice (first on row {$seen[$vsku]}).");

                continue;
            }
            $seen[$vsku] = $r;
            $owner = ProductVariant::where('sku', $vsku)->value('product_id');
            if ($owner && $owner !== $existing?->id) {
                $err('variant_sku', "Variant SKU {$vsku} is already used by another product.");
            }
            if ($vsku === $sku) {
                $err('variant_sku', 'Variant SKU must be different from the product SKU.');
            }
            $options = null;
            if (! empty($v['options'])) {
                $options = [];
                foreach ($this->split((string) $v['options'], '/\s*[|\n;]\s*/u') as $pair) {
                    [$k, $val] = array_map('trim', array_pad(explode(':', $pair, 2), 2, ''));
                    $k !== '' && $val !== '' ? $options[$k] = $val : $err('options', "\"{$pair}\" should be written as Option: Value (for example Colour: Red).");
                }
            }
            $adj = 0.0;
            if (($v['price_adjustment'] ?? null) !== null && $v['price_adjustment'] !== '') {
                $adj = $this->number($v['price_adjustment']);
                if ($adj === null) {
                    $err('price_adjustment', 'Price difference must be a number, e.g. 150 or -100.');
                    $adj = 0.0;
                }
            }
            $active = true;
            if (($v['is_active'] ?? null) !== null && $v['is_active'] !== '') {
                $active = $this->bool($v['is_active']);
                if ($active === null) {
                    $err('is_active', 'Active must be Yes or No.');
                    $active = true;
                }
            }
            $i = count($out);
            $out[] = array_filter([
                'id' => $current->get($vsku)?->id,
                'sku' => $vsku,
                'name' => isset($v['variant_name']) ? trim((string) $v['variant_name']) : '',
                'options' => $options,
                'price_adjustment' => $adj,
                'is_active' => $active,
            ], fn ($x) => $x !== null);
            foreach (['sku' => 'variant_sku', 'name' => 'variant_name', 'options' => 'options', 'price_adjustment' => 'price_adjustment', 'is_active' => 'is_active'] as $field => $col) {
                $locator["variants.{$i}.{$field}"] = [ImportSchema::VARIANTS, $r, $col];
            }
        }

        return $out;
    }

    private function mapFaqs(string $sku, array $rows, array &$locator): array
    {
        $out = [];
        foreach ($rows as $row) {
            $i = count($out);
            $out[] = ['question' => trim((string) ($row['values']['question'] ?? '')), 'answer' => trim((string) ($row['values']['answer'] ?? ''))];
            $locator["faqs.{$i}.question"] = [ImportSchema::FAQS, $row['row'], 'question'];
            $locator["faqs.{$i}.answer"] = [ImportSchema::FAQS, $row['row'], 'answer'];
        }

        return $out;
    }

    // ── Validation ────────────────────────────────────────────────
    private function validate(string $sku, array $data, ?Product $existing, array $locator, array $anchor): void
    {
        $rules = ProductRequest::fieldRules($existing?->id, $existing === null);
        $names = [];
        foreach (ImportSchema::columns(ImportSchema::PRODUCTS) as $k => $c) {
            $names[$k] = $c[0];
        }
        $names += ['category_id' => 'Category', 'brand_id' => 'Brand', 'attribute_value_ids' => 'Filters', 'initial_stock' => 'Stock on hand',
            'compatibilities.*.vehicle_manufacturer_id' => 'Vehicle', 'compatibilities.*.vehicle_model_id' => 'Vehicle', 'compatibilities.*.vehicle_variant_id' => 'Vehicle',
            'compatibilities.*.year_from' => 'Year from', 'compatibilities.*.year_to' => 'Year to', 'compatibilities.*.notes' => 'Notes',
            'variants.*.sku' => 'Variant SKU', 'variants.*.name' => 'Variant name', 'variants.*.price_adjustment' => 'Price difference',
            'faqs.*.question' => 'Question', 'faqs.*.answer' => 'Answer', 'specifications.*.label' => 'Specification label', 'specifications.*.value' => 'Specification value',
            'whats_included.*' => "What's included item", 'tags.*' => 'Tag'];

        $validator = Validator::make($data, $rules, ['sku.regex' => 'SKU may only contain letters, numbers, dashes and underscores.'], $names);
        $errors = $validator->errors()->messages();

        $mrp = $data['mrp'] ?? $existing?->mrp;
        $price = $data['price'] ?? $existing?->price;
        if (ProductRequest::priceAboveMrp($mrp, $price)) {
            $errors['price'][] = 'Selling price ('.$price.') cannot be higher than MRP ('.$mrp.').';
        }

        foreach ($errors as $key => $messages) {
            [$sheet, $row, $col] = $this->locate($key, $locator, $anchor);
            if ($col !== null && isset($this->reported[$sheet.'|'.$row.'|'.$col])) {
                continue;
            }
            foreach ($messages as $m) {
                $this->issue('error', $sheet, $row, $sku, $col, $m);
            }
        }
    }

    private function locate(string $key, array $locator, array $anchor): array
    {
        if (isset($locator[$key])) {
            return $locator[$key];
        }
        // "specifications.2.label" → specifications column, "compatibilities.3" → that row.
        $parts = explode('.', $key);
        for ($n = count($parts) - 1; $n >= 1; $n--) {
            $prefix = implode('.', array_slice($parts, 0, $n));
            foreach ($locator as $k => $loc) {
                if ($k === $prefix || str_starts_with($k, $prefix.'.')) {
                    return $loc;
                }
            }
        }

        return [$anchor[0], $anchor[1], $key === 'sku' ? 'sku' : null];
    }

    // ── Images ────────────────────────────────────────────────────
    private function syncImages(Product $product, array $urls, int $row): void
    {
        $current = $product->images()->get();
        $byUrl = [];
        foreach ($current as $img) {
            $byUrl[Media::url($img->path)] = $img;
            $byUrl[$img->path] = $img;
        }

        $keep = [];
        $failed = 0;
        foreach ($urls as $i => $url) {
            if (isset($byUrl[$url])) {
                $keep[] = $byUrl[$url];

                continue;
            }
            try {
                [$bytes, $mime] = $this->fetcher->fetch($url);
                $keep[] = $product->images()->create([
                    'path' => $this->uploads->storeContents($bytes, $mime, 'products'),
                    'alt' => $product->name, 'is_primary' => false, 'sort_order' => 0,
                ]);
            } catch (ImageFetchException $e) {
                $failed++;
                $this->issue('warning', ImportSchema::PRODUCTS, $row, $product->sku, 'image_urls', 'Image '.($i + 1).' was not added: '.$e->getMessage().'.');
            } catch (\Throwable $e) {
                $failed++;
                Log::warning('Import image failed', ['url' => $url, 'error' => $e->getMessage()]);
                $this->issue('warning', ImportSchema::PRODUCTS, $row, $product->sku, 'image_urls', 'Image '.($i + 1).' was not added: it could not be saved.');
            }
        }
        if (! $keep && $failed && $current->isNotEmpty()) {
            $this->issue('warning', ImportSchema::PRODUCTS, $row, $product->sku, 'image_urls', 'No new image could be downloaded, so the current photos were kept.');

            return;
        }

        $keepIds = array_map(fn (ProductImage $i) => $i->id, $keep);
        foreach ($current as $img) {
            if (! in_array($img->id, $keepIds, true)) {
                $this->uploads->delete($img->path);
                $img->delete();
            }
        }
        foreach ($keep as $i => $img) {
            $img->forceFill(['sort_order' => $i + 1, 'is_primary' => $i === 0])->save();
        }
        $product->touch();
    }

    // ── Progress & result ─────────────────────────────────────────
    private function shouldFlush(int $done, int $total): bool
    {
        return $done === $total || microtime(true) - $this->lastFlush >= 0.75;
    }

    private function flush(int $done): void
    {
        $this->lastFlush = microtime(true);
        $this->import->forceFill([
            'processed_rows' => $done,
            'created_count' => $this->counts['created'],
            'updated_count' => $this->counts['updated'],
            'skipped_count' => $this->counts['skipped'],
            'failed_count' => $this->counts['failed'],
            'warning_count' => $this->warningCount,
            'error_count' => $this->errorCount,
            'errors' => $this->issues,
        ])->save();
    }

    private function finish(array $sheets, bool $cancelled): void
    {
        $import = $this->import;
        // Present problems in file order: sheet by sheet, row by row.
        $order = array_flip(ImportSchema::dataSheets());
        usort($this->issues, fn ($a, $b) => [$order[$a['sheet']] ?? 9, $a['row'] ?? 0] <=> [$order[$b['sheet']] ?? 9, $b['row'] ?? 0]);
        $import->forceFill(['errors' => $this->issues]);
        $rows = [
            'products' => count($sheets[ImportSchema::PRODUCTS]),
            'compatibility' => count($sheets[ImportSchema::COMPATIBILITY]),
            'variants' => count($sheets[ImportSchema::VARIANTS]),
            'faqs' => count($sheets[ImportSchema::FAQS]),
        ];

        if ($this->dryRun) {
            $import->forceFill([
                'status' => $cancelled ? ProductImport::CANCELLED : ProductImport::VALIDATED,
                'validated_at' => now(),
                'finished_at' => $cancelled ? now() : null,
                'summary' => [
                    'will_create' => $this->counts['created'],
                    'will_update' => $this->counts['updated'],
                    'will_skip' => $this->counts['skipped'],
                    'invalid' => $this->counts['failed'],
                    'errors' => $this->errorCount,
                    'warnings' => $this->warningCount,
                    'images' => $this->imageCount,
                    'rows' => $rows,
                ],
                'message' => $cancelled ? 'Check cancelled.' : null,
            ])->save();

            return;
        }

        $ok = $this->counts['created'] + $this->counts['updated'];
        $import->forceFill([
            'status' => $cancelled ? ProductImport::CANCELLED : ($this->counts['failed'] > 0 ? ProductImport::COMPLETED_WITH_ERRORS : ProductImport::COMPLETED),
            'finished_at' => now(),
            'message' => $cancelled ? "Import cancelled — {$ok} product(s) were saved before it stopped." : null,
        ])->save();
    }

    // ── Helpers ───────────────────────────────────────────────────
    private function issue(string $level, string $sheet, ?int $row, ?string $sku, ?string $column, string $message): void
    {
        if ($level === 'error') {
            $this->errorCount++;
            $this->unitFailed = true;
            if ($column !== null) {
                $this->reported[$sheet.'|'.$row.'|'.$column] = true;
            }
        } else {
            $this->warningCount++;
        }
        if (count($this->issues) < ProductImport::MAX_STORED_ERRORS) {
            $this->issues[] = [
                'level' => $level,
                'sheet' => $sheet,
                'row' => $row,
                'sku' => $sku,
                'column' => $column ? ImportSchema::header($sheet, $column) : null,
                'message' => $message,
            ];
        }
    }

    private function firstChildAnchor(array $unit): array
    {
        $first = $this->childRows($unit)[0] ?? [ImportSchema::PRODUCTS, ['row' => null]];

        return [$first[0], $first[1]['row']];
    }

    /** @return list<array{0: string, 1: array}> */
    private function childRows(array $unit): array
    {
        $out = [];
        foreach ([ImportSchema::COMPATIBILITY => 'compat', ImportSchema::VARIANTS => 'variants', ImportSchema::FAQS => 'faqs'] as $sheet => $bucket) {
            foreach ($unit[$bucket] as $r) {
                $out[] = [$sheet, $r];
            }
        }

        return $out;
    }

    private function sku(mixed $v): string
    {
        return strtoupper(trim((string) ($v ?? '')));
    }

    private function bool(mixed $v): ?bool
    {
        if (is_bool($v)) {
            return $v;
        }
        $s = strtolower(trim((string) $v));

        return in_array($s, self::BOOL_TRUE, true) ? true : (in_array($s, self::BOOL_FALSE, true) ? false : null);
    }

    private function number(mixed $v): ?float
    {
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = str_replace(['₹', ',', ' ', "\u{00A0}", 'Rs.', 'Rs', 'INR'], '', trim((string) $v));
        $s = str_replace(['−', '–'], '-', $s);

        return is_numeric($s) ? (float) $s : null;
    }

    private function split(string $text, string $pattern): array
    {
        return array_values(array_filter(array_map('trim', preg_split($pattern, trim($text)) ?: []), fn ($x) => $x !== ''));
    }
}
