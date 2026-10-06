<?php

namespace Database\Seeders;

use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\VehicleManufacturer;
use App\Services\InventoryService;
use App\Services\ProductSearchIndexer;
use Database\Seeders\Support\Art;
use Database\Seeders\Support\ProductCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    private const CODES = [
        'filters' => 'FLT', 'brake-system' => 'BRK', 'suspension' => 'SUS', 'engine-parts' => 'ENG', 'transmission' => 'TRN', 'lighting' => 'LGT',
        'exterior-accessories' => 'EXT', 'cooling' => 'COL', 'steering' => 'STR', 'exhaust' => 'EXH', 'electrical' => 'ELC', 'batteries' => 'BAT',
        'chain-sprocket' => 'CHN', 'brake-parts' => 'BBR', 'touring' => 'TOU', 'bike-exhaust' => 'BEX', 'bike-lighting' => 'BLG', 'bike-suspension' => 'BSU',
        'bike-electrical' => 'BEL', 'performance' => 'PRF', 'interior-accessories' => 'INT', 'electronics' => 'ELE', 'utility' => 'UTL', 'travel' => 'TRV',
        'cleaning-care' => 'CLN', 'safety' => 'SAF', 'engine-oil' => 'OIL', 'fluids-lubricants' => 'FLU',
    ];

    /** product_id => desired available stock after demo orders are seeded (see OrderSeeder). */
    public static array $targetStock = [];

    public function run(): void
    {
        mt_srand(2026);
        $brands = Brand::pluck('id', 'name');
        $categories = Category::with('parent')->get()->keyBy('slug');
        $manufacturers = VehicleManufacturer::with('models.variants')->get()->keyBy('name');
        $attrValues = AttributeValue::with('attribute')->get()->groupBy(fn ($v) => $v->attribute->name)->map(fn ($g) => $g->keyBy('value'));
        $inventory = app(InventoryService::class);
        $indexer = app(ProductSearchIndexer::class);
        $seq = [];

        foreach (ProductCatalog::all() as $i => $row) {
            $cat = $categories[$row['category']];
            $code = self::CODES[$row['category']] ?? 'GEN';
            $seq[$code] = ($seq[$code] ?? 0) + 1;
            $sku = sprintf('MG-%s-%04d', $code, $seq[$code] * 7 + 100);
            $slug = Str::slug(str_replace(['&', '–'], ['and', ''], $row['name']));
            $isUniversal = $row['fits'] === 'universal';

            $product = Product::updateOrCreate(['sku' => $sku], [
                'category_id' => $cat->id,
                'brand_id' => $brands[$row['brand']],
                'name' => $row['name'],
                'slug' => $slug,
                'part_number' => $this->partNumber($row['brand'], $i),
                'short_description' => $this->short($row, $cat->name),
                'description' => $this->description($row, $cat),
                'cost_price' => round($row['price'] * (mt_rand(55, 72) / 100)),
                'mrp' => $row['mrp'],
                'price' => $row['price'],
                'tax_rate' => in_array($row['category'], ['batteries', 'engine-oil', 'fluids-lubricants'], true) ? 28 : 18,
                'vehicle_type' => $row['vehicle_type'],
                'is_universal' => $isUniversal,
                'position' => $row['position'] ?? null,
                'material' => $row['material'] ?? null,
                'dimensions' => $row['dimensions'] ?? $this->dimensions($row['icon']),
                'weight_kg' => $row['weight'] ?? round(mt_rand(15, 450) / 100, 2),
                'warranty' => $row['warranty'] ?? '6 months manufacturer warranty',
                'installation_info' => $row['installation'] ?? $this->installation($row),
                'whats_included' => $row['included'] ?? ['1 × '.$row['name'], 'Product manual'],
                'specifications' => collect($row['specs'] ?? [])->map(fn ($v, $k) => ['label' => $k, 'value' => (string) $v])->values()->all(),
                'tags' => $row['tags'] ?? [],
                'meta_title' => Str::limit($row['name'], 60, '').' | '.$row['brand'].' | MotoGears',
                'meta_description' => 'Buy '.$row['name'].' online at ₹'.number_format($row['price']).'. Genuine '.$row['brand'].' part with fitment guarantee, fast delivery and easy returns.',
                'is_active' => true,
                'is_featured' => (bool) ($row['featured'] ?? false),
            ]);
            $product->forceFill([
                'created_at' => now()->subDays(mt_rand(3, 200))->subMinutes(mt_rand(0, 1440)),
                'view_count' => mt_rand(40, 4000),
            ])->saveQuietly();

            // Images: hero, detail, in-the-box
            if (! $product->images()->exists()) {
                foreach ([0, 1, 2] as $v) {
                    $path = Art::product("products/{$slug}-{$v}.svg", $row['name'], $row['brand'], $row['icon'], $row['palette'], $v);
                    $product->images()->create(['path' => $path, 'alt' => $row['name'].(['', ' – detail', ' – in the box'][$v]), 'is_primary' => $v === 0, 'sort_order' => $v]);
                }
            }

            // Vehicle compatibility
            $product->compatibilities()->delete();
            if (! $isUniversal) {
                foreach ($row['fits'] as $fit) {
                    $this->addFitment($product, $manufacturers, ...array_pad($fit, 6, null));
                }
            }

            // Attributes
            $ids = [];
            foreach ($row['attrs'] ?? [] as $attr => $value) {
                if ($v = $attrValues[$attr][$value] ?? null) {
                    $ids[] = $v->id;
                }
            }
            $product->attributeValues()->sync($ids);

            // FAQs
            $product->faqs()->delete();
            foreach ($this->faqs($row, $isUniversal) as $j => [$q, $a]) {
                $product->faqs()->create(['question' => $q, 'answer' => $a, 'sort_order' => $j]);
            }

            // Colour-temperature variants for a few lighting products
            if ($row['category'] === 'lighting' && str_contains($row['name'], 'Bulb') && ! $product->variants()->exists()) {
                foreach (['4300K Warm White' => 0, '6000K Cool White' => 200] as $label => $adj) {
                    $product->variants()->create(['sku' => $sku.'-'.substr($label, 0, 5), 'name' => $label, 'options' => ['Colour temperature' => explode(' ', $label)[0]], 'price_adjustment' => $adj]);
                }
            }

            // Stock: most items healthy, some low, a few out of stock.
            if (! $product->inventory) {
                $roll = mt_rand(1, 100);
                $qty = match (true) {
                    $roll <= 4 => 0,
                    $roll <= 12 => mt_rand(2, 4),
                    default => mt_rand(25, 160),
                };
                $inv = $inventory->setInitialStock($product, $qty + 60, mt_rand(5, 8), $roll === 3);
                // Keep the realistic distribution after seeded orders consume stock (see OrderSeeder).
                $inv->forceFill(['location' => 'BLR-'.chr(65 + $i % 6).'-'.str_pad((string) ($i % 40 + 1), 2, '0', STR_PAD_LEFT)])->save();
                self::$targetStock[$product->id] = $qty;
            }

            $indexer->index($product);
        }
    }

    private function addFitment(Product $product, $manufacturers, string $make, ?string $modelName, ?string $fuel, ?int $yFrom, ?int $yTo, ?string $transmission): void
    {
        $m = $manufacturers[$make];
        if (! $modelName) {
            $product->compatibilities()->create(['vehicle_manufacturer_id' => $m->id, 'year_from' => $yFrom, 'year_to' => $yTo]);

            return;
        }
        $model = $m->models->firstWhere('name', $modelName) ?? throw new \RuntimeException("Unknown model {$make} {$modelName}");
        if (! $fuel && ! $transmission && ! $yFrom) {
            $from = $model->variants->min('year_from');
            $to = $model->variants->contains(fn ($v) => $v->year_to === null) ? null : $model->variants->max('year_to');
            $product->compatibilities()->create(['vehicle_manufacturer_id' => $m->id, 'vehicle_model_id' => $model->id, 'year_from' => $from, 'year_to' => $to]);

            return;
        }
        $variants = $model->variants
            ->when($fuel, fn ($c) => $c->where('fuel_type', $fuel))
            ->when($transmission, fn ($c) => $c->where('transmission', $transmission))
            ->when($yFrom, fn ($c) => $c->filter(fn ($v) => ($v->year_to ?? 9999) >= $yFrom));
        foreach ($variants as $v) {
            $product->compatibilities()->create([
                'vehicle_manufacturer_id' => $m->id, 'vehicle_model_id' => $model->id, 'vehicle_variant_id' => $v->id,
                'year_from' => max($v->year_from, $yFrom ?? 0), 'year_to' => $v->year_to,
            ]);
        }
    }

    private function partNumber(string $brand, int $i): string
    {
        $n = str_pad((string) (($i * 7919) % 10000), 4, '0', STR_PAD_LEFT);

        return match ($brand) {
            'Bosch' => '0 986 AB'.$n,
            'Mann-Filter' => 'C '.substr($n, 0, 2).' '.substr($n, 2).'/1',
            'K&N' => '33-'.$n,
            'Brembo' => 'P 30 '.substr($n, 0, 3),
            'NGK' => 'ILKR7'.chr(65 + $i % 8).'-'.substr($n, 0, 2),
            'Denso' => 'DN-'.$n.'-IK',
            'Philips' => '129'.substr($n, 0, 2).'XV+S2',
            'Hella' => '1NA 0'.substr($n, 0, 2).' '.substr($n, 2).'-801',
            'Monroe' => 'G'.$n,
            'Gabriel' => 'GAB-'.$n,
            'Valeo' => 'VAL-8'.$n,
            'Rolon' => 'RL-KIT-'.$n,
            'Akrapovic' => 'S-KT'.substr($n, 0, 2).'SO'.substr($n, 2).'-HAPT',
            'Motul' => '10'.$n.'M',
            'Castrol' => 'CS-'.$n,
            default => strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $brand), 0, 3)).'-'.$n,
        };
    }

    private function short(array $r, string $category): string
    {
        $fit = $r['fits'] === 'universal' ? 'Universal fit' : 'Direct fit';

        return "{$fit} {$category} from {$r['brand']}. ".(($r['warranty'] ?? null) ? "Warranty: {$r['warranty']}." : 'Genuine product with fitment support.');
    }

    private function description(array $r, Category $cat): string
    {
        $parent = $cat->parent?->name ?? $cat->name;
        $specs = collect($r['specs'] ?? [])->map(fn ($v, $k) => "<li><strong>{$k}:</strong> {$v}</li>")->implode('');
        $fits = $r['fits'] === 'universal'
            ? '<p>This is a <strong>universal</strong> product that works with most '.($r['vehicle_type'] === 'motorcycle' ? 'motorcycles and scooters' : ($r['vehicle_type'] === 'car' ? 'cars and SUVs' : 'cars and two-wheelers')).'.</p>'
            : '<p>Engineered for a precise, bolt-on fit. Check the <strong>Compatibility</strong> tab or select your vehicle to confirm fitment before ordering.</p>';

        $intro = match ($r['icon']) {
            'pad', 'disc' => "Stop confidently with the {$r['name']}. Formulated for Indian traffic and highway conditions, it delivers consistent bite, low dust and quiet operation from the first stop.",
            'filter' => "Keep contaminants out and performance up with the {$r['name']}. The pleated media traps fine dust common on Indian roads while maintaining optimum airflow.",
            'headlight', 'bulb' => "See further and be seen with the {$r['name']}. Brighter, whiter light improves night-time visibility on unlit highways and in heavy rain.",
            'shock' => "Restore ride comfort and handling with the {$r['name']}. Gas-charged damping controls body roll and absorbs potholes and speed breakers.",
            'sprocket', 'chain' => "Smooth, efficient power delivery with the {$r['name']}. Precision-machined teeth and sealed chain rollers extend service life and reduce maintenance.",
            'oil', 'drop', 'spray' => "Protect and maintain your machine with {$r['name']}. Trusted by mechanics and enthusiasts for consistent quality.",
            'battery' => "Reliable starts every morning with the {$r['name']}. Maintenance-free construction with excellent cranking power even in extreme heat.",
            'bag', 'guard' => "Built for long rides across India, the {$r['name']} is rugged, weather-resistant and designed by riders for riders.",
            default => "The {$r['name']} from {$r['brand']} is a quality {$parent} product built to last.",
        };

        return "<p>{$intro}</p>{$fits}".($specs ? "<h3>Key specifications</h3><ul>{$specs}</ul>" : '')
            .'<h3>Why buy from MotoGears?</h3><ul><li>100% genuine products sourced from authorised distributors</li><li>Fitment guarantee — free return if it does not fit your selected vehicle</li><li>GST invoice with every order</li></ul>';
    }

    private function installation(array $r): string
    {
        return match ($r['icon']) {
            'pad', 'disc' => 'Professional installation recommended. Bed-in new pads with 10 moderate stops from 60 km/h. Replace pads on both wheels of an axle together.',
            'filter' => 'DIY-friendly: open the housing clips, remove the old element, clean the housing and insert the new filter with the seal facing the housing. Takes about 10 minutes.',
            'headlight', 'bulb' => 'Plug-and-play installation with no wire cutting. Aim the beams after fitting to avoid dazzling oncoming traffic.',
            'shock' => 'Requires a spring compressor for strut assemblies. We recommend fitting at a workshop and getting a wheel alignment afterwards.',
            'sprocket', 'chain' => 'Workshop installation recommended. Set chain slack to 25–35 mm and lubricate every 500 km.',
            'battery' => 'Disconnect the negative terminal first and reconnect it last. Free installation available at partner garages.',
            'oil' => 'Drain old oil with the engine warm, replace the oil filter, and refill to the level recommended in your owner\'s manual.',
            default => 'Easy to install with basic hand tools. Detailed instructions are included in the box.',
        };
    }

    private function dimensions(string $icon): ?string
    {
        return match ($icon) {
            'filter' => '28 × 20 × 5 cm', 'pad' => '15 × 6 × 2 cm', 'disc' => '30 × 30 × 3 cm', 'battery' => '24 × 17 × 20 cm',
            'bag' => '45 × 35 × 25 cm', 'shock' => '45 × 10 × 10 cm', default => null,
        };
    }

    private function faqs(array $r, bool $universal): array
    {
        $faqs = [
            ['Is this a genuine product?', 'Yes. All MotoGears products are sourced directly from '.$r['brand'].' or its authorised distributors and come with a GST invoice.'],
            ['What if it does not fit my vehicle?', 'If you selected your vehicle and the part does not fit, we will pick it up free of charge and give you a full refund within 7 days of delivery.'],
        ];
        $faqs[] = $universal
            ? ['Will this fit my vehicle?', 'It is designed as a universal product. Check the dimensions and specifications, or contact support with your vehicle details.']
            : ['How do I check compatibility?', 'Use the vehicle selector at the top of the page or open the Compatibility tab to see every make, model, variant and year this part fits.'];

        return $faqs;
    }
}
