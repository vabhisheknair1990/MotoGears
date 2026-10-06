<?php

namespace Database\Seeders;

use App\Models\Attribute;
use App\Models\Brand;
use App\Models\Category;
use Database\Seeders\Support\Art;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CatalogSeeder extends Seeder
{
    /** root => [slug, vehicle_type, icon, palette, description, children[[name, slug, icon, palette]]] */
    public const CATEGORIES = [
        'Car Parts' => ['car-parts', 'car', 'car', 'default', 'OE-quality replacement parts for Indian cars and SUVs.', [
            ['Engine Parts', 'engine-parts', 'gear', 'default'],
            ['Brake System', 'brake-system', 'disc', 'brake'],
            ['Suspension', 'suspension', 'shock', 'suspension'],
            ['Steering', 'steering', 'steering', 'default'],
            ['Electrical', 'electrical', 'battery', 'electrical'],
            ['Filters', 'filters', 'filter', 'filter'],
            ['Cooling', 'cooling', 'radiator', 'care'],
            ['Transmission', 'transmission', 'clutch', 'default'],
            ['Exhaust', 'exhaust', 'exhaust', 'exhaust'],
            ['Lighting', 'lighting', 'headlight', 'light'],
        ]],
        'Motorcycle Parts' => ['motorcycle-parts', 'motorcycle', 'bike', 'brake', 'Genuine-fit parts and upgrades for motorcycles and scooters.', [
            ['Brake Parts', 'brake-parts', 'pad', 'brake'],
            ['Chain & Sprocket', 'chain-sprocket', 'sprocket', 'chain'],
            ['Bike Exhaust', 'bike-exhaust', 'exhaust', 'exhaust'],
            ['Bike Lighting', 'bike-lighting', 'bulb', 'light'],
            ['Bike Suspension', 'bike-suspension', 'shock', 'suspension'],
            ['Bike Electrical', 'bike-electrical', 'usb', 'electrical'],
            ['Performance', 'performance', 'filter', 'exhaust'],
            ['Touring', 'touring', 'bag', 'touring'],
        ]],
        'Accessories' => ['accessories', 'universal', 'tools', 'interior', 'Interior, exterior, electronics, care and safety accessories.', [
            ['Interior', 'interior-accessories', 'mat', 'interior'],
            ['Exterior', 'exterior-accessories', 'car', 'exterior'],
            ['Electronics', 'electronics', 'camera', 'electrical'],
            ['Cleaning & Care', 'cleaning-care', 'spray', 'care'],
            ['Safety', 'safety', 'shield', 'safety'],
            ['Travel', 'travel', 'bag', 'touring'],
            ['Utility', 'utility', 'tools', 'default'],
        ]],
        'Oils & Maintenance' => ['oils-maintenance', 'universal', 'oil', 'oil', 'Engine oils, lubricants, fluids and batteries.', [
            ['Engine Oil', 'engine-oil', 'oil', 'oil'],
            ['Fluids & Lubricants', 'fluids-lubricants', 'drop', 'oil'],
            ['Batteries', 'batteries', 'battery', 'battery'],
        ]],
    ];

    /** name => [country, website, featured, colour, description] */
    public const BRANDS = [
        'Bosch' => ['Germany', 'https://www.bosch.in', true, '#dc2626', 'German engineering for brakes, filters, wipers, spark plugs and electricals.'],
        'Hella' => ['Germany', 'https://www.hella.com', true, '#111827', 'Lighting and electronics specialist trusted by carmakers worldwide.'],
        'Philips' => ['Netherlands', 'https://www.philips.co.in', true, '#1d4ed8', 'Automotive lighting — halogen, LED and Xtreme Vision upgrades.'],
        'Osram' => ['Germany', 'https://www.osram.com', false, '#ea580c', 'Premium automotive bulbs and LED retrofits.'],
        'Brembo' => ['Italy', 'https://www.brembo.com', true, '#dc2626', 'High-performance braking systems for cars and motorcycles.'],
        'Motul' => ['France', 'https://www.motul.com', true, '#b91c1c', 'Synthetic engine oils and lubricants, born in racing.'],
        'Castrol' => ['United Kingdom', 'https://www.castrol.com', true, '#15803d', 'Engine oils and transmission fluids for every vehicle.'],
        'Mobil 1' => ['USA', 'https://www.mobil.com', false, '#1e3a8a', 'Advanced full-synthetic motor oils.'],
        'Denso' => ['Japan', 'https://www.denso.com', false, '#dc2626', 'OE supplier of spark plugs, radiators and electricals.'],
        'NGK' => ['Japan', 'https://www.ngksparkplugs.com', false, '#1f2937', 'The world\'s leading spark plug manufacturer.'],
        'Mann-Filter' => ['Germany', 'https://www.mann-filter.com', true, '#15803d', 'Air, oil, fuel and cabin filters in OE quality.'],
        'K&N' => ['USA', 'https://www.knfilters.com', true, '#dc2626', 'Washable, reusable high-flow performance air filters.'],
        'Monroe' => ['USA', 'https://www.monroe.com', false, '#1d4ed8', 'Shock absorbers and struts for a safer ride.'],
        'Gabriel' => ['India', 'https://www.gabrielindia.com', false, '#b91c1c', 'India\'s leading ride-control products maker.'],
        'Rolon' => ['India', 'https://www.rolonchains.com', false, '#374151', 'Chains and sprockets for Indian two-wheelers.'],
        'Exide' => ['India', 'https://www.exideindustries.com', true, '#dc2626', 'Automotive and motorcycle batteries.'],
        'Amaron' => ['India', 'https://www.amaron.com', true, '#15803d', 'Zero-maintenance batteries with long warranties.'],
        'Valeo' => ['France', 'https://www.valeo.com', false, '#16a34a', 'Clutch kits, wipers and thermal systems.'],
        'Uno Minda' => ['India', 'https://www.unominda.com', false, '#1d4ed8', 'Lighting, horns, switches and accessories.'],
        'Liqui Moly' => ['Germany', 'https://www.liqui-moly.com', false, '#1e3a8a', 'Oils, additives and car care from Germany.'],
        '3M' => ['USA', 'https://www.3m.com', true, '#dc2626', 'Car care, detailing and protection products.'],
        'Meguiar\'s' => ['USA', 'https://www.meguiars.com', false, '#111827', 'Car wash, polish and wax trusted by detailers.'],
        'Michelin' => ['France', 'https://www.michelin.in', false, '#1d4ed8', 'Tyre inflators, wipers and travel accessories.'],
        'Akrapovic' => ['Slovenia', 'https://www.akrapovic.com', true, '#111827', 'Titanium performance exhaust systems.'],
        'Viaterra' => ['India', 'https://www.viaterragear.com', false, '#57534e', 'Motorcycle touring luggage and riding gear.'],
        'Rynox' => ['India', 'https://www.rynoxgear.com', false, '#ea580c', 'Weatherproof motorcycle bags and riding gear.'],
        'Studds' => ['India', 'https://www.studds.com', false, '#111827', 'Helmets and rider safety equipment.'],
        'Endurance' => ['India', 'https://www.endurancegroup.com', false, '#1d4ed8', 'Brakes, suspension and clutches for two-wheelers.'],
    ];

    public const ATTRIBUTES = [
        'Position' => ['Front', 'Rear', 'Front & Rear'],
        'Colour Temperature' => ['3000K', '4300K', '6000K', '6500K'],
        'Viscosity' => ['10W-30', '10W-40', '10W-50', '5W-30', '5W-40', '0W-40', '15W-40', '20W-50'],
        'Fitment Type' => ['OE Replacement', 'Performance Upgrade', 'Accessory'],
        'Material' => ['Ceramic', 'Semi-metallic', 'Titanium', 'Stainless Steel', 'Cotton Gauze', 'Synthetic', 'Aluminium'],
    ];

    public function run(): void
    {
        $sort = 0;
        foreach (self::CATEGORIES as $name => [$slug, $type, $icon, $palette, $description, $children]) {
            $root = Category::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'vehicle_type' => $type, 'icon' => $icon, 'description' => $description,
                'image_path' => Art::tile("categories/{$slug}.svg", $name, $icon, $palette),
                'seo_title' => $name.' Online in India | MotoGears', 'seo_description' => $description,
                'is_active' => true, 'is_featured' => true, 'sort_order' => $sort++,
            ]);
            foreach ($children as $i => [$childName, $childSlug, $childIcon, $childPalette]) {
                Category::updateOrCreate(['slug' => $childSlug], [
                    'parent_id' => $root->id, 'name' => $childName, 'vehicle_type' => $type, 'icon' => $childIcon,
                    'description' => "Shop {$childName} for ".($type === 'universal' ? 'cars and bikes' : ($type === 'car' ? 'cars & SUVs' : 'motorcycles')).' from trusted brands, with fitment checked for your vehicle.',
                    'image_path' => Art::tile("categories/{$childSlug}.svg", $childName, $childIcon, $childPalette),
                    'seo_title' => "{$childName} | {$name} | MotoGears",
                    'is_active' => true,
                    'is_featured' => in_array($childSlug, ['brake-system', 'lighting', 'filters', 'suspension', 'chain-sprocket', 'touring', 'engine-oil', 'electronics', 'cleaning-care', 'brake-parts', 'batteries', 'performance'], true),
                    'sort_order' => $i,
                ]);
            }
        }

        $i = 0;
        foreach (self::BRANDS as $name => [$country, $website, $featured, $colour, $description]) {
            $slug = Str::slug(str_replace(['&', '\''], ['and', ''], $name));
            Brand::updateOrCreate(['slug' => $slug], [
                'name' => $name, 'country' => $country, 'website' => $website, 'description' => $description,
                'logo_path' => Art::wordmark("brands/{$slug}.svg", $name, '#111827', $colour),
                'is_active' => true, 'is_featured' => $featured, 'sort_order' => $i++,
                'seo_title' => "{$name} Auto Parts & Accessories | MotoGears",
                'seo_description' => $description,
            ]);
        }

        $i = 0;
        foreach (self::ATTRIBUTES as $name => $values) {
            $attr = Attribute::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'is_filterable' => true, 'sort_order' => $i++]);
            foreach ($values as $j => $value) {
                $attr->values()->updateOrCreate(['slug' => Str::slug($value)], ['value' => $value, 'sort_order' => $j]);
            }
        }
    }
}
