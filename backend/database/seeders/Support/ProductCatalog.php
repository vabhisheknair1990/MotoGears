<?php

namespace Database\Seeders\Support;

/**
 * Realistic Indian-market catalogue: model-specific parts generated per vehicle plus
 * universal accessories. Each entry is a plain array consumed by ProductSeeder.
 *
 * fits: list of [manufacturer, model, fuel|null, yearFrom|null, yearTo|null]  (model null = all models)
 */
class ProductCatalog
{
    public static function all(): array
    {
        return array_merge(self::carParts(), self::bikeParts(), self::accessories(), self::oils());
    }

    private static function carParts(): array
    {
        $p = [];

        // Air filters
        $air = [
            ['Mahindra', 'Thar', 'Mann-Filter', 649, 489], ['Mahindra', 'Scorpio-N', 'Mann-Filter', 699, 529], ['Mahindra', 'XUV700', 'Bosch', 749, 569],
            ['Hyundai', 'Creta', 'Bosch', 549, 399], ['Hyundai', 'Venue', 'Bosch', 499, 369], ['Hyundai', 'i20', 'Mann-Filter', 479, 349],
            ['Tata', 'Nexon', 'Mann-Filter', 529, 389], ['Tata', 'Harrier', 'Bosch', 799, 599], ['Tata', 'Punch', 'Bosch', 449, 329],
            ['Toyota', 'Fortuner', 'Denso', 999, 749], ['Toyota', 'Innova Crysta', 'Denso', 949, 699], ['Maruti Suzuki', 'Swift', 'Bosch', 399, 289],
            ['Maruti Suzuki', 'Baleno', 'Mann-Filter', 429, 309], ['Kia', 'Seltos', 'Bosch', 549, 409], ['Honda', 'City', 'Denso', 599, 449],
        ];
        foreach ($air as [$make, $model, $brand, $mrp, $price]) {
            $p[] = self::part("{$brand} Engine Air Filter for {$make} {$model}", $brand, 'filters', $mrp, $price, [[$make, $model]], 'filter', 'filter', [
                'material' => 'Synthetic', 'warranty' => '6 months or 10,000 km', 'attrs' => ['Fitment Type' => 'OE Replacement', 'Material' => 'Synthetic'],
                'specs' => ['Filter type' => 'Panel', 'Filtration efficiency' => '99.5%', 'Service interval' => '10,000 km'],
                'tags' => ['air filter', strtolower($model), 'service'],
            ]);
        }
        // K&N performance air filters
        foreach ([['Mahindra', 'Thar', 5990, 4990], ['Toyota', 'Fortuner', 6490, 5490], ['Hyundai', 'Creta', 4990, 4290], ['Maruti Suzuki', 'Swift', 3990, 3490]] as [$make, $model, $mrp, $price]) {
            $p[] = self::part("K&N High-Flow Performance Air Filter – {$make} {$model}", 'K&N', 'filters', $mrp, $price, [[$make, $model]], 'filter', 'exhaust', [
                'material' => 'Cotton Gauze', 'warranty' => '10 years / 1,000,000 km', 'featured' => $model === 'Thar',
                'attrs' => ['Fitment Type' => 'Performance Upgrade', 'Material' => 'Cotton Gauze'],
                'specs' => ['Filter type' => 'Washable & reusable', 'Airflow gain' => 'Up to 50% over paper', 'Cleaning interval' => '80,000 km'],
                'tags' => ['performance', 'k&n', 'reusable', strtolower($model)],
            ]);
        }
        // Cabin filters
        foreach ([['Hyundai', 'Creta', 699, 499], ['Tata', 'Nexon', 649, 459], ['Kia', 'Seltos', 699, 499], ['Honda', 'City', 749, 549], ['Mahindra', 'XUV700', 799, 599], ['Toyota', 'Fortuner', 899, 649], ['Maruti Suzuki', 'Brezza', 549, 399]] as [$make, $model, $mrp, $price]) {
            $p[] = self::part("{$make} {$model} Cabin Filter with Activated Carbon", $make === 'Toyota' ? 'Denso' : 'Bosch', 'filters', $mrp, $price, [[$make, $model]], 'filter', 'care', [
                'warranty' => '6 months', 'attrs' => ['Fitment Type' => 'OE Replacement'],
                'specs' => ['Layers' => '3 (incl. activated carbon)', 'PM2.5 filtration' => '95%', 'Replacement' => 'Every 10,000 km or yearly'],
                'tags' => ['cabin filter', 'ac filter', 'pm2.5', strtolower($model)],
            ]);
        }
        // Oil filters
        foreach ([['Mahindra', 'Thar', 'Diesel', 449, 339], ['Hyundai', 'Creta', null, 349, 259], ['Maruti Suzuki', 'Swift', null, 249, 179], ['Toyota', 'Fortuner', 'Diesel', 599, 449], ['Toyota', 'Innova Crysta', 'Diesel', 549, 419], ['Tata', 'Nexon', null, 329, 239]] as [$make, $model, $fuel, $mrp, $price]) {
            $p[] = self::part("Mann-Filter Oil Filter – {$make} {$model}".($fuel ? " {$fuel}" : ''), 'Mann-Filter', 'filters', $mrp, $price, [[$make, $model, $fuel]], 'filter', 'oil', [
                'warranty' => '6 months', 'attrs' => ['Fitment Type' => 'OE Replacement'],
                'specs' => ['Type' => 'Spin-on / cartridge', 'Anti-drain valve' => 'Yes', 'Change interval' => 'Every oil change'],
                'tags' => ['oil filter', 'service', strtolower($model)],
            ]);
        }
        // Brake pads
        $pads = [
            ['Mahindra', 'Thar', 'Bosch', 2499, 1899, 'Ceramic'], ['Hyundai', 'Creta', 'Bosch', 2199, 1649, 'Ceramic'], ['Tata', 'Nexon', 'Bosch', 1999, 1499, 'Semi-metallic'],
            ['Maruti Suzuki', 'Swift', 'Bosch', 1499, 1099, 'Semi-metallic'], ['Toyota', 'Fortuner', 'Brembo', 4299, 3599, 'Ceramic'], ['Kia', 'Seltos', 'Brembo', 3299, 2799, 'Ceramic'],
            ['Mahindra', 'XUV700', 'Brembo', 3999, 3399, 'Ceramic'], ['Tata', 'Harrier', 'Bosch', 2799, 2149, 'Ceramic'], ['Honda', 'City', 'Bosch', 1899, 1449, 'Ceramic'],
            ['Mahindra', 'Scorpio-N', 'Brembo', 4199, 3499, 'Ceramic'], ['Hyundai', 'Venue', 'Bosch', 1899, 1399, 'Semi-metallic'],
        ];
        foreach ($pads as [$make, $model, $brand, $mrp, $price, $mat]) {
            $name = $brand === 'Bosch' ? "Bosch Premium Front Brake Pad Set – {$make} {$model}" : "Brembo {$mat} Front Brake Pads – {$make} {$model}";
            $p[] = self::part($name, $brand, 'brake-system', $mrp, $price, [[$make, $model]], 'pad', 'brake', [
                'position' => 'Front', 'material' => $mat, 'warranty' => '1 year or 20,000 km', 'featured' => in_array($model, ['Thar', 'Creta', 'Fortuner'], true),
                'attrs' => ['Position' => 'Front', 'Material' => $mat, 'Fitment Type' => $brand === 'Brembo' ? 'Performance Upgrade' : 'OE Replacement'],
                'specs' => ['Axle' => 'Front', 'Pieces' => '4 pads (one axle)', 'Wear indicator' => 'Acoustic', 'Friction coefficient' => '0.42 μ', 'ECE R90 certified' => 'Yes'],
                'included' => ['4 × brake pads', 'Anti-squeal shims', 'Fitting instructions'],
                'tags' => ['brake pads', 'brakes', strtolower($model), strtolower($brand)],
            ]);
        }
        // Brake discs
        foreach ([['Mahindra', 'Thar', 5499, 4499], ['Toyota', 'Fortuner', 7999, 6799], ['Mahindra', 'Scorpio-N', 6299, 5299], ['Hyundai', 'Creta', 4499, 3699]] as [$make, $model, $mrp, $price]) {
            $p[] = self::part("Brembo Ventilated Front Brake Disc (Pair) – {$make} {$model}", 'Brembo', 'brake-system', $mrp, $price, [[$make, $model]], 'disc', 'brake', [
                'position' => 'Front', 'material' => 'Cast iron, UV-coated', 'warranty' => '2 years',
                'attrs' => ['Position' => 'Front', 'Fitment Type' => 'OE Replacement'],
                'specs' => ['Type' => 'Ventilated', 'Coating' => 'UV anti-corrosion', 'Sold as' => 'Pair'],
                'included' => ['2 × brake discs'], 'tags' => ['brake disc', 'rotor', strtolower($model)],
            ]);
        }
        // Rear brake shoes
        foreach ([['Maruti Suzuki', 'Swift', 1199, 849], ['Tata', 'Punch', 1299, 949], ['Maruti Suzuki', 'Baleno', 1199, 869]] as [$make, $model, $mrp, $price]) {
            $p[] = self::part("Bosch Rear Brake Shoe Set – {$make} {$model}", 'Bosch', 'brake-system', $mrp, $price, [[$make, $model]], 'pad', 'brake', [
                'position' => 'Rear', 'warranty' => '1 year', 'attrs' => ['Position' => 'Rear', 'Fitment Type' => 'OE Replacement'],
                'specs' => ['Axle' => 'Rear drum', 'Pieces' => '4 shoes'], 'tags' => ['brake shoe', 'drum brake', strtolower($model)],
            ]);
        }
        // Shock absorbers
        foreach ([
            ['Mahindra', 'Thar', 'Front', 'Monroe', 4999, 4199], ['Mahindra', 'Thar', 'Rear', 'Monroe', 4799, 3999], ['Toyota', 'Fortuner', 'Front', 'Monroe', 7499, 6299],
            ['Hyundai', 'Creta', 'Rear', 'Gabriel', 2999, 2399], ['Maruti Suzuki', 'Swift', 'Front', 'Gabriel', 2599, 2049], ['Tata', 'Nexon', 'Front', 'Gabriel', 2899, 2299],
        ] as [$make, $model, $pos, $brand, $mrp, $price]) {
            $p[] = self::part("{$brand} {$pos} Shock Absorber – {$make} {$model}", $brand, 'suspension', $mrp, $price, [[$make, $model]], 'shock', 'suspension', [
                'position' => $pos, 'warranty' => '1 year or 30,000 km', 'attrs' => ['Position' => $pos, 'Fitment Type' => 'OE Replacement'],
                'specs' => ['Type' => 'Twin-tube gas charged', 'Sold as' => 'Single unit', 'Mounting' => 'Direct bolt-on'],
                'tags' => ['shock absorber', 'suspension', 'strut', strtolower($model)],
            ]);
        }
        // Spark plugs (petrol only)
        foreach ([['Maruti Suzuki', 'Swift', 'NGK', 1196, 899], ['Hyundai', 'i20', 'NGK', 1196, 929], ['Honda', 'City', 'Denso', 2396, 1899], ['Hyundai', 'Creta', 'NGK', 1596, 1249], ['Mahindra', 'Thar', 'Denso', 2796, 2299], ['Kia', 'Seltos', 'NGK', 1596, 1249]] as [$make, $model, $brand, $mrp, $price]) {
            $p[] = self::part("{$brand} Iridium Spark Plug Set of 4 – {$make} {$model} Petrol", $brand, 'engine-parts', $mrp, $price, [[$make, $model, 'Petrol']], 'spark', 'electrical', [
                'warranty' => '6 months or 40,000 km', 'attrs' => ['Fitment Type' => 'OE Replacement'],
                'specs' => ['Electrode' => 'Iridium fine-wire', 'Gap' => 'Pre-gapped', 'Quantity' => '4 plugs'],
                'included' => ['4 × spark plugs'], 'tags' => ['spark plug', 'ignition', 'iridium', strtolower($model)],
            ]);
        }
        // Clutch kits
        foreach ([['Mahindra', 'Thar', 'Diesel', 14999, 12499], ['Maruti Suzuki', 'Swift', null, 6999, 5599], ['Tata', 'Nexon', null, 9999, 8299], ['Hyundai', 'Creta', null, 10999, 8999]] as [$make, $model, $fuel, $mrp, $price]) {
            $p[] = self::part("Valeo 3-Piece Clutch Kit – {$make} {$model}".($fuel ? " {$fuel}" : ''), 'Valeo', 'transmission', $mrp, $price, [[$make, $model, $fuel, null, null, 'MT']], 'clutch', 'default', [
                'warranty' => '1 year or 30,000 km', 'attrs' => ['Fitment Type' => 'OE Replacement'],
                'specs' => ['Kit contents' => 'Clutch plate, pressure plate, release bearing', 'Transmission' => 'Manual only'],
                'included' => ['Clutch disc', 'Pressure plate', 'Release bearing', 'Alignment tool'],
                'tags' => ['clutch', 'clutch plate', 'transmission', strtolower($model)],
            ]);
        }
        // Lighting (model-specific)
        foreach ([
            ['Mahindra Thar LED Headlight Kit', 'Philips', 'Mahindra', 'Thar', 12999, 9999, '6000K', true],
            ['Mahindra Scorpio-N LED Projector Headlamp Upgrade', 'Hella', 'Mahindra', 'Scorpio-N', 18999, 15999, '6000K', false],
            ['Maruti Swift LED Headlight Bulb Pair (H4)', 'Philips', 'Maruti Suzuki', 'Swift', 4499, 3299, '6500K', false],
            ['Hyundai Creta LED Fog Lamp Assembly', 'Hella', 'Hyundai', 'Creta', 5999, 4799, '6000K', false],
            ['Toyota Fortuner LED Tail Lamp Set', 'Uno Minda', 'Toyota', 'Fortuner', 15999, 12999, '6000K', false],
            ['Mahindra Thar LED DRL Fog Lamp Pair', 'Hella', 'Mahindra', 'Thar', 7499, 5999, '6000K', true],
            ['Tata Nexon Philips Xtreme Vision Headlight Bulb Pair', 'Philips', 'Tata', 'Nexon', 2799, 2199, '4300K', false],
        ] as [$name, $brand, $make, $model, $mrp, $price, $k, $featured]) {
            $p[] = self::part($name, $brand, 'lighting', $mrp, $price, [[$make, $model]], str_contains($name, 'Bulb') ? 'bulb' : 'headlight', 'light', [
                'warranty' => '2 years', 'featured' => $featured, 'attrs' => ['Colour Temperature' => $k, 'Fitment Type' => 'Performance Upgrade'],
                'specs' => ['Colour temperature' => $k, 'Voltage' => '12V', 'Waterproof rating' => 'IP67', 'Plug & play' => 'Yes, no wire cutting'],
                'tags' => ['led', 'headlight', 'lighting', strtolower($model)],
            ]);
        }
        // Universal-ish lighting
        $p[] = self::part('Hella LED Fog Lamp 90mm (Pair)', 'Hella', 'lighting', 6999, 5499, 'universal', 'headlight', 'light', [
            'vehicle_type' => 'car', 'warranty' => '2 years', 'featured' => true, 'attrs' => ['Colour Temperature' => '6000K', 'Fitment Type' => 'Accessory'],
            'specs' => ['Diameter' => '90 mm', 'Power' => '2 × 12W', 'Lumen' => '2 × 700 lm', 'Waterproof' => 'IP67'], 'tags' => ['fog lamp', 'led', 'universal'],
        ]);
        $p[] = self::part('Philips Xtreme Vision H4 Headlight Bulb (Pair)', 'Philips', 'lighting', 2399, 1899, 'universal', 'bulb', 'light', [
            'vehicle_type' => 'car', 'warranty' => '1 year', 'attrs' => ['Colour Temperature' => '3000K', 'Fitment Type' => 'OE Replacement'],
            'specs' => ['Socket' => 'H4', 'Brightness' => 'Up to 130% more light', 'Wattage' => '60/55W'], 'tags' => ['h4', 'halogen', 'bulb', 'headlight'],
        ]);
        $p[] = self::part('Osram Night Breaker H7 LED Bulb (Pair)', 'Osram', 'lighting', 5999, 4999, 'universal', 'bulb', 'light', [
            'vehicle_type' => 'car', 'warranty' => '2 years', 'attrs' => ['Colour Temperature' => '6000K'],
            'specs' => ['Socket' => 'H7', 'Brightness' => '+230%', 'Lifespan' => '5x halogen'], 'tags' => ['h7', 'led', 'bulb'],
        ]);
        // Wiper blades
        foreach ([['Hyundai', 'Creta', 1299, 999], ['Tata', 'Nexon', 1199, 899], ['Maruti Suzuki', 'Swift', 999, 749], ['Mahindra', 'XUV700', 1499, 1149]] as [$make, $model, $mrp, $price]) {
            $p[] = self::part("Bosch Aerotwin Wiper Blade Set – {$make} {$model}", 'Bosch', 'exterior-accessories', $mrp, $price, [[$make, $model]], 'wiper', 'exterior', [
                'warranty' => '6 months', 'attrs' => ['Fitment Type' => 'OE Replacement'],
                'specs' => ['Type' => 'Flat (beam) blade', 'Sold as' => 'Driver + passenger pair', 'Rubber' => 'Power Protection Plus'],
                'tags' => ['wiper', 'wiper blade', strtolower($model)],
            ]);
        }
        // Cooling & steering & exhaust & electrical
        $p[] = self::part('Denso Radiator Assembly – Maruti Suzuki Swift', 'Denso', 'cooling', 7499, 5999, [['Maruti Suzuki', 'Swift']], 'radiator', 'care', ['warranty' => '1 year', 'specs' => ['Core' => 'Aluminium', 'Rows' => '1'], 'tags' => ['radiator', 'cooling', 'swift']]);
        $p[] = self::part('Valeo Thermostat with Housing – Hyundai Creta Diesel', 'Valeo', 'cooling', 2299, 1799, [['Hyundai', 'Creta', 'Diesel']], 'radiator', 'care', ['warranty' => '1 year', 'specs' => ['Opening temperature' => '88°C'], 'tags' => ['thermostat', 'cooling', 'creta']]);
        $p[] = self::part('Tie Rod End (Pair) – Mahindra Thar', 'Bosch', 'steering', 2199, 1699, [['Mahindra', 'Thar']], 'steering', 'default', ['position' => 'Front', 'warranty' => '1 year', 'attrs' => ['Position' => 'Front'], 'specs' => ['Sold as' => 'Left + right pair'], 'tags' => ['tie rod', 'steering', 'thar']]);
        $p[] = self::part('Steering Rack Boot Kit – Toyota Innova Crysta', 'Valeo', 'steering', 1299, 999, [['Toyota', 'Innova Crysta']], 'steering', 'default', ['warranty' => '6 months', 'specs' => ['Material' => 'Neoprene rubber'], 'tags' => ['steering boot', 'innova']]);
        $p[] = self::part('Stainless Steel Performance Exhaust Tip – Mahindra Thar', 'Akrapovic', 'exhaust', 8999, 7499, [['Mahindra', 'Thar']], 'exhaust', 'exhaust', ['material' => 'Stainless Steel', 'warranty' => '2 years', 'attrs' => ['Material' => 'Stainless Steel', 'Fitment Type' => 'Performance Upgrade'], 'specs' => ['Finish' => 'Brushed', 'Outlet' => '76 mm'], 'tags' => ['exhaust', 'thar', 'performance']]);
        $p[] = self::part('Bosch Alternator 90A – Mahindra Scorpio-N Diesel', 'Bosch', 'electrical', 18999, 15499, [['Mahindra', 'Scorpio-N', 'Diesel']], 'battery', 'electrical', ['warranty' => '1 year', 'specs' => ['Output' => '90 A', 'Voltage' => '12 V'], 'tags' => ['alternator', 'scorpio']]);
        $p[] = self::part('Bosch Dual-Tone Horn Set (12V)', 'Bosch', 'electrical', 1899, 1399, 'universal', 'usb', 'electrical', ['vehicle_type' => 'car', 'warranty' => '1 year', 'specs' => ['Sound level' => '110–118 dB', 'Frequency' => '410/510 Hz'], 'tags' => ['horn', 'electrical']]);
        $p[] = self::part('Timing Belt Kit – Honda City 1.5 i-VTEC', 'Valeo', 'engine-parts', 5999, 4799, [['Honda', 'City', 'Petrol']], 'gear', 'default', ['warranty' => '1 year or 40,000 km', 'specs' => ['Contents' => 'Belt, tensioner, idler'], 'tags' => ['timing belt', 'city']]);
        $p[] = self::part('Engine Mount Set – Tata Harrier', 'Endurance', 'engine-parts', 6499, 5299, [['Tata', 'Harrier']], 'gear', 'default', ['warranty' => '1 year', 'specs' => ['Pieces' => '3 mounts'], 'tags' => ['engine mount', 'harrier']]);

        // Batteries
        foreach ([
            ['Amaron Pro 65Ah Car Battery', 'Amaron', 8999, 7499, [['Mahindra', 'Thar'], ['Mahindra', 'Scorpio-N'], ['Tata', 'Harrier'], ['Toyota', 'Innova Crysta']], '65Ah', '60 months'],
            ['Exide Mileage 35Ah Car Battery', 'Exide', 4999, 3999, [['Maruti Suzuki', 'Swift'], ['Maruti Suzuki', 'Baleno'], ['Hyundai', 'i20'], ['Tata', 'Punch']], '35Ah', '48 months'],
            ['Amaron Hi-Life 45Ah Car Battery', 'Amaron', 6299, 5199, [['Hyundai', 'Creta'], ['Kia', 'Seltos'], ['Honda', 'City'], ['Tata', 'Nexon'], ['Hyundai', 'Venue']], '45Ah', '55 months'],
            ['Exide Xpress 80Ah SUV Battery', 'Exide', 10999, 9199, [['Toyota', 'Fortuner'], ['Mahindra', 'XUV700']], '80Ah', '48 months'],
        ] as [$name, $brand, $mrp, $price, $fits, $cap, $war]) {
            $p[] = self::part($name, $brand, 'batteries', $mrp, $price, $fits, 'battery', 'battery', [
                'vehicle_type' => 'car', 'warranty' => $war.' (pro-rata)', 'specs' => ['Capacity' => $cap, 'Voltage' => '12 V', 'Type' => 'Maintenance-free SMF'],
                'installation' => 'Free installation at partner garages in 30+ cities. Exchange your old battery for an additional discount.',
                'tags' => ['battery', 'car battery', strtolower($brand)],
            ]);
        }

        return $p;
    }

    private static function bikeParts(): array
    {
        $p = [];
        foreach ([
            ['Royal Enfield', 'Classic 350', 3299, 2599], ['Royal Enfield', 'Himalayan', 3699, 2999], ['KTM', 'Duke 390', 4499, 3799], ['KTM', 'Duke 200', 3499, 2849],
            ['Bajaj', 'Pulsar NS200', 2799, 2199], ['TVS', 'Apache RTR 160 4V', 2299, 1799], ['Yamaha', 'R15 V4', 2499, 1999], ['Bajaj', 'Dominar 400', 3999, 3299],
            ['Royal Enfield', 'Interceptor 650', 4999, 4199], ['Royal Enfield', 'Hunter 350', 3199, 2549],
        ] as [$make, $model, $mrp, $price]) {
            $p[] = self::part("Rolon O-Ring Chain Sprocket Kit – {$make} {$model}", 'Rolon', 'chain-sprocket', $mrp, $price, [[$make, $model]], 'sprocket', 'chain', [
                'vehicle_type' => 'motorcycle', 'warranty' => '6 months or 15,000 km', 'featured' => $model === 'Classic 350',
                'attrs' => ['Fitment Type' => 'OE Replacement'],
                'specs' => ['Chain type' => 'O-ring sealed', 'Sprocket material' => 'Hardened steel', 'Kit' => 'Chain + front & rear sprockets'],
                'included' => ['Drive chain', 'Front sprocket', 'Rear sprocket', 'Master link'],
                'tags' => ['chain sprocket', 'chain kit', strtolower($model)],
            ]);
        }
        foreach ([['KTM', 'Duke 390', 'Brembo', 2499, 1999], ['Royal Enfield', 'Himalayan', 'Brembo', 2299, 1849], ['Royal Enfield', 'Classic 350', 'Endurance', 999, 749], ['Royal Enfield', 'Interceptor 650', 'Brembo', 2699, 2199], ['Yamaha', 'R15 V4', 'Endurance', 899, 679], ['Bajaj', 'Pulsar NS200', 'Endurance', 849, 649]] as [$make, $model, $brand, $mrp, $price]) {
            $p[] = self::part("{$brand} Sintered Front Brake Pads – {$make} {$model}", $brand, 'brake-parts', $mrp, $price, [[$make, $model]], 'pad', 'brake', [
                'vehicle_type' => 'motorcycle', 'position' => 'Front', 'material' => 'Sintered', 'warranty' => '6 months',
                'attrs' => ['Position' => 'Front', 'Fitment Type' => $brand === 'Brembo' ? 'Performance Upgrade' : 'OE Replacement'],
                'specs' => ['Compound' => 'Sintered metal', 'Use' => 'Street & touring'], 'tags' => ['brake pads', 'bike brakes', strtolower($model)],
            ]);
        }
        foreach ([
            ['Royal Enfield Classic Crash Guard', [['Royal Enfield', 'Classic 350']], 3499, 2799, true],
            ['Royal Enfield Himalayan Crash Guard with Slider', [['Royal Enfield', 'Himalayan']], 4999, 4199, false],
            ['Royal Enfield Hunter 350 Engine Guard', [['Royal Enfield', 'Hunter 350']], 3299, 2699, false],
            ['KTM Duke 390 Frame Sliders', [['KTM', 'Duke 390']], 2999, 2399, false],
        ] as [$name, $fits, $mrp, $price, $featured]) {
            $p[] = self::part($name, 'Viaterra', 'touring', $mrp, $price, $fits, 'guard', 'touring', [
                'vehicle_type' => 'motorcycle', 'material' => 'Mild steel, powder-coated', 'warranty' => '1 year', 'featured' => $featured,
                'attrs' => ['Fitment Type' => 'Accessory'], 'specs' => ['Tube diameter' => '25 mm', 'Finish' => 'Matte black powder coat', 'Mounting' => 'Bolt-on, no drilling'],
                'included' => ['Crash guard', 'Mounting hardware'], 'tags' => ['crash guard', 'protection', 'touring'],
            ]);
        }
        $p[] = self::part('Akrapovic Slip-On Titanium Exhaust – KTM Duke 390', 'Akrapovic', 'bike-exhaust', 64999, 58999, [['KTM', 'Duke 390']], 'exhaust', 'exhaust', ['vehicle_type' => 'motorcycle', 'material' => 'Titanium', 'featured' => true, 'warranty' => '2 years', 'attrs' => ['Material' => 'Titanium', 'Fitment Type' => 'Performance Upgrade'], 'specs' => ['Weight saving' => '1.8 kg', 'Power gain' => '+1.2 hp'], 'tags' => ['exhaust', 'akrapovic', 'duke 390', 'performance']]);
        $p[] = self::part('Akrapovic Slip-On Line Exhaust (Pair) – Interceptor 650', 'Akrapovic', 'bike-exhaust', 89999, 82999, [['Royal Enfield', 'Interceptor 650']], 'exhaust', 'exhaust', ['vehicle_type' => 'motorcycle', 'material' => 'Stainless Steel', 'warranty' => '2 years', 'attrs' => ['Material' => 'Stainless Steel', 'Fitment Type' => 'Performance Upgrade'], 'tags' => ['exhaust', 'interceptor', 'twin']]);
        $p[] = self::part('Royal Enfield Classic 350 LED Headlight', 'Philips', 'bike-lighting', 3999, 3199, [['Royal Enfield', 'Classic 350'], ['Royal Enfield', 'Bullet 350']], 'headlight', 'light', ['vehicle_type' => 'motorcycle', 'warranty' => '1 year', 'attrs' => ['Colour Temperature' => '6000K'], 'specs' => ['Size' => '7 inch round', 'DRL' => 'Halo ring'], 'tags' => ['led headlight', 'classic 350']]);
        $p[] = self::part('Hella Motorcycle LED Auxiliary Lights (Pair)', 'Hella', 'bike-lighting', 5999, 4799, 'universal', 'bulb', 'light', ['vehicle_type' => 'motorcycle', 'warranty' => '1 year', 'featured' => true, 'attrs' => ['Colour Temperature' => '6000K', 'Fitment Type' => 'Accessory'], 'specs' => ['Power' => '2 × 40W', 'Beam' => 'Spot + flood', 'Waterproof' => 'IP68'], 'tags' => ['aux lights', 'fog lights', 'touring']]);
        $p[] = self::part('Gabriel Rear Shock Absorber Pair – Royal Enfield Classic 350', 'Gabriel', 'bike-suspension', 3999, 3199, [['Royal Enfield', 'Classic 350']], 'shock', 'suspension', ['vehicle_type' => 'motorcycle', 'position' => 'Rear', 'warranty' => '1 year', 'attrs' => ['Position' => 'Rear'], 'specs' => ['Preload' => '5-step adjustable'], 'tags' => ['shock absorber', 'suspension', 'classic 350']]);
        $p[] = self::part('Endurance Front Fork Oil Seal Kit – Royal Enfield Himalayan', 'Endurance', 'bike-suspension', 899, 699, [['Royal Enfield', 'Himalayan']], 'shock', 'suspension', ['vehicle_type' => 'motorcycle', 'position' => 'Front', 'attrs' => ['Position' => 'Front'], 'tags' => ['fork seal', 'himalayan']]);
        $p[] = self::part('Motorcycle Dual USB Fast Charger (QC 3.0)', 'Uno Minda', 'bike-electrical', 1299, 899, 'universal', 'usb', 'electrical', ['vehicle_type' => 'motorcycle', 'warranty' => '6 months', 'specs' => ['Output' => 'QC 3.0 + 2.4A', 'Voltmeter' => 'Yes', 'Waterproof' => 'IP66'], 'tags' => ['usb charger', 'mobile charger', 'bike']]);
        $p[] = self::part('Exide Xplore 9Ah Motorcycle Battery', 'Exide', 'bike-electrical', 2499, 1999, [['Royal Enfield', 'Classic 350'], ['Royal Enfield', 'Hunter 350'], ['Royal Enfield', 'Bullet 350'], ['Bajaj', 'Dominar 400']], 'battery', 'battery', ['vehicle_type' => 'motorcycle', 'warranty' => '36 months', 'specs' => ['Capacity' => '9Ah', 'Type' => 'VRLA'], 'tags' => ['bike battery']]);
        $p[] = self::part('Amaron Pro Bike Rider 5Ah Battery', 'Amaron', 'bike-electrical', 1799, 1449, [['TVS', 'Apache RTR 160 4V'], ['Yamaha', 'FZ-S FI V4'], ['Hero', 'Splendor Plus'], ['Honda', 'Activa 6G'], ['TVS', 'Jupiter']], 'battery', 'battery', ['vehicle_type' => 'motorcycle', 'warranty' => '48 months', 'specs' => ['Capacity' => '5Ah'], 'tags' => ['bike battery', 'scooter battery']]);
        foreach ([['KTM', 'Duke 390', 4999, 4299], ['Royal Enfield', 'Interceptor 650', 5499, 4699], ['Royal Enfield', 'Classic 350', 3999, 3399]] as [$make, $model, $mrp, $price]) {
            $p[] = self::part("K&N Performance Air Filter – {$make} {$model}", 'K&N', 'performance', $mrp, $price, [[$make, $model]], 'filter', 'exhaust', ['vehicle_type' => 'motorcycle', 'material' => 'Cotton Gauze', 'warranty' => '10 years', 'attrs' => ['Material' => 'Cotton Gauze', 'Fitment Type' => 'Performance Upgrade'], 'tags' => ['k&n', 'air filter', 'performance', strtolower($model)]]);
        }
        $p[] = self::part('Motorcycle Touring Saddle Bag (Pair, 60L)', 'Viaterra', 'touring', 9999, 8499, 'universal', 'bag', 'touring', ['vehicle_type' => 'motorcycle', 'featured' => true, 'warranty' => '1 year', 'specs' => ['Capacity' => '2 × 30 L', 'Material' => '1680D ballistic nylon', 'Rain cover' => 'Included'], 'included' => ['2 × saddle bags', '2 × rain covers', 'Mounting straps'], 'tags' => ['saddle bag', 'touring', 'luggage']]);
        $p[] = self::part('Rynox Expedition Tank Bag 25L', 'Rynox', 'touring', 6499, 5499, 'universal', 'bag', 'touring', ['vehicle_type' => 'motorcycle', 'warranty' => '1 year', 'specs' => ['Capacity' => '25 L expandable', 'Mount' => 'Magnetic + strap', 'Waterproof' => 'Yes, welded seams'], 'tags' => ['tank bag', 'touring']]);
        $p[] = self::part('Top Box Mounting Plate – Royal Enfield Himalayan 450', 'Viaterra', 'touring', 3999, 3299, [['Royal Enfield', 'Himalayan', null, 2023, null]], 'guard', 'touring', ['vehicle_type' => 'motorcycle', 'warranty' => '1 year', 'tags' => ['top box', 'himalayan', 'touring']]);
        $p[] = self::part('Studds Motorcycle Cover – Waterproof', 'Studds', 'touring', 999, 699, 'universal', 'bag', 'touring', ['vehicle_type' => 'motorcycle', 'tags' => ['bike cover']]);

        return $p;
    }

    private static function accessories(): array
    {
        $u = fn (string $name, string $brand, string $cat, int $mrp, int $price, string $icon, string $pal, array $extra = []) => self::part($name, $brand, $cat, $mrp, $price, 'universal', $icon, $pal, $extra + ['vehicle_type' => 'universal']);

        return [
            $u('Universal Car Phone Holder – Magnetic Dash Mount', 'Uno Minda', 'interior-accessories', 1299, 799, 'phone', 'interior', ['vehicle_type' => 'car', 'featured' => true, 'specs' => ['Mount' => 'Dashboard / windshield', 'Rotation' => '360°', 'Compatible phones' => '4–7 inch'], 'tags' => ['phone holder', 'mobile holder', 'mount']]),
            $u('Wireless Charging Phone Mount 15W', 'Philips', 'electronics', 2999, 2199, 'phone', 'electrical', ['vehicle_type' => 'car', 'specs' => ['Output' => '15 W Qi', 'Mount' => 'AC vent', 'Auto-clamp' => 'Yes'], 'tags' => ['wireless charger', 'phone mount']]),
            $u('Philips GoSure 1080p Dash Camera', 'Philips', 'electronics', 7999, 5999, 'camera', 'electrical', ['vehicle_type' => 'car', 'featured' => true, 'warranty' => '1 year', 'specs' => ['Resolution' => '1080p Full HD', 'Field of view' => '140°', 'Night vision' => 'Yes', 'Storage' => 'microSD up to 128 GB'], 'tags' => ['dash cam', 'dashcam', 'camera']]),
            $u('Michelin Digital Tyre Inflator 12V', 'Michelin', 'utility', 4999, 3799, 'pump', 'exterior', ['featured' => true, 'warranty' => '1 year', 'specs' => ['Max pressure' => '100 PSI', 'Display' => 'Digital with auto cut-off', 'Power' => '12 V socket'], 'tags' => ['tyre inflator', 'air pump', 'compressor']]),
            $u('Bosch Car Jump Starter & Power Bank 12,000mAh', 'Bosch', 'utility', 8999, 6999, 'battery', 'battery', ['vehicle_type' => 'car', 'warranty' => '1 year', 'specs' => ['Peak current' => '600 A', 'Capacity' => '12,000 mAh', 'Engines' => 'Up to 3.0L petrol / 2.0L diesel'], 'tags' => ['jump starter', 'battery booster']]),
            $u('Premium 3D Floor Mats – Hyundai Creta', 'Uno Minda', 'interior-accessories', 4999, 3699, 'mat', 'interior', ['vehicle_type' => 'car', 'fits' => [['Hyundai', 'Creta']], 'specs' => ['Material' => 'TPE, odourless', 'Pieces' => '5'], 'tags' => ['floor mats', '3d mats', 'creta']]),
            $u('Premium 3D Floor Mats – Tata Nexon', 'Uno Minda', 'interior-accessories', 4799, 3499, 'mat', 'interior', ['vehicle_type' => 'car', 'fits' => [['Tata', 'Nexon']], 'specs' => ['Material' => 'TPE', 'Pieces' => '5'], 'tags' => ['floor mats', 'nexon']]),
            $u('Mahindra Thar Seat Covers – Leatherette', 'Uno Minda', 'interior-accessories', 11999, 8999, 'mat', 'interior', ['vehicle_type' => 'car', 'fits' => [['Mahindra', 'Thar']], 'featured' => true, 'specs' => ['Material' => 'Nappa-grain leatherette', 'Airbag compatible' => 'Yes'], 'tags' => ['seat covers', 'thar']]),
            $u('Hyundai Creta Car Body Cover – Waterproof', 'Uno Minda', 'exterior-accessories', 1999, 1399, 'car', 'exterior', ['vehicle_type' => 'car', 'fits' => [['Hyundai', 'Creta']], 'tags' => ['body cover', 'car cover', 'creta']]),
            $u('Mahindra Thar Car Body Cover – Heavy Duty', 'Uno Minda', 'exterior-accessories', 2499, 1799, 'car', 'exterior', ['vehicle_type' => 'car', 'fits' => [['Mahindra', 'Thar']], 'tags' => ['body cover', 'thar']]),
            $u('Aluminium Roof Carrier Rack (Universal)', 'Uno Minda', 'travel', 8999, 7299, 'car', 'touring', ['vehicle_type' => 'car', 'material' => 'Aluminium', 'attrs' => ['Material' => 'Aluminium'], 'specs' => ['Load capacity' => '60 kg', 'Length' => '120 cm'], 'tags' => ['roof carrier', 'roof rack', 'luggage']]),
            $u('Collapsible Boot Organiser', 'Uno Minda', 'utility', 1499, 999, 'bag', 'touring', ['vehicle_type' => 'car', 'specs' => ['Compartments' => '3', 'Foldable' => 'Yes'], 'tags' => ['trunk organizer', 'boot organiser']]),
            $u('3M Car Shampoo 1L', '3M', 'cleaning-care', 599, 449, 'spray', 'care', ['specs' => ['pH' => 'Neutral', 'Volume' => '1 litre'], 'tags' => ['car wash', 'shampoo', 'cleaning']]),
            $u('Meguiar\'s Ultimate Liquid Wax 473ml', 'Meguiar\'s', 'cleaning-care', 2499, 1999, 'spray', 'care', ['featured' => true, 'specs' => ['Volume' => '473 ml', 'Protection' => 'Synthetic polymer'], 'tags' => ['wax', 'polish', 'detailing']]),
            $u('3M Microfibre Cloth (Pack of 3)', '3M', 'cleaning-care', 599, 399, 'spray', 'care', ['specs' => ['Size' => '40 × 40 cm', 'GSM' => '350'], 'tags' => ['microfiber', 'cloth', 'cleaning']]),
            $u('Meguiar\'s Quik Interior Detailer 473ml', 'Meguiar\'s', 'cleaning-care', 1499, 1199, 'spray', 'care', ['tags' => ['interior cleaner', 'dashboard']]),
            $u('3M Tyre Dresser 250ml', '3M', 'cleaning-care', 499, 379, 'spray', 'care', ['tags' => ['tyre shine', 'tyre polish']]),
            $u('Bosch Cordless Car Vacuum Cleaner', 'Bosch', 'cleaning-care', 6999, 5499, 'pump', 'care', ['vehicle_type' => 'car', 'warranty' => '1 year', 'specs' => ['Suction' => '8 kPa', 'Battery' => '2,000 mAh Li-ion'], 'tags' => ['vacuum cleaner', 'car vacuum']]),
            $u('Car First Aid Kit – 70 Pieces', 'Studds', 'safety', 1299, 899, 'shield', 'safety', ['specs' => ['Pieces' => '70', 'Compliance' => 'AIS-096'], 'tags' => ['first aid', 'safety kit']]),
            $u('ABC Fire Extinguisher 1kg with Car Mount', 'Studds', 'safety', 1999, 1499, 'shield', 'safety', ['vehicle_type' => 'car', 'specs' => ['Type' => 'ABC dry powder', 'Capacity' => '1 kg', 'Certification' => 'ISI'], 'tags' => ['fire extinguisher', 'safety']]),
            $u('Reflective Warning Triangle', 'Uno Minda', 'safety', 699, 449, 'shield', 'safety', ['vehicle_type' => 'car', 'tags' => ['warning triangle', 'breakdown']]),
            $u('Studds Thunder D7 Full-Face Helmet', 'Studds', 'safety', 2499, 1999, 'helmet', 'safety', ['vehicle_type' => 'motorcycle', 'featured' => true, 'warranty' => '6 months', 'specs' => ['Certification' => 'ISI & DOT', 'Visor' => 'Anti-scratch clear'], 'tags' => ['helmet', 'full face', 'riding gear']]),
            $u('Heavy-Duty Tow Rope 5 Tonne', 'Bosch', 'utility', 1499, 1099, 'chain', 'default', ['vehicle_type' => 'car', 'specs' => ['Capacity' => '5,000 kg', 'Length' => '4 m'], 'tags' => ['tow rope', 'recovery']]),
            $u('Car Sunshade Set (4 Windows)', 'Uno Minda', 'interior-accessories', 999, 699, 'car', 'interior', ['vehicle_type' => 'car', 'tags' => ['sunshade', 'window shade']]),
            $u('Car Seat Back Organiser with Tablet Holder', 'Uno Minda', 'travel', 1299, 899, 'bag', 'touring', ['vehicle_type' => 'car', 'tags' => ['organiser', 'travel']]),
            $u('Bluetooth FM Transmitter & Car Charger', 'Philips', 'electronics', 1799, 1299, 'usb', 'electrical', ['vehicle_type' => 'car', 'specs' => ['Bluetooth' => '5.3', 'Ports' => 'USB-C PD 20W + USB-A'], 'tags' => ['fm transmitter', 'bluetooth', 'charger']]),
            $u('Tyre Pressure Monitoring System (Solar)', 'Michelin', 'electronics', 5999, 4599, 'pump', 'electrical', ['vehicle_type' => 'car', 'specs' => ['Sensors' => '4 external', 'Power' => 'Solar + USB'], 'tags' => ['tpms', 'tyre pressure']]),
        ];
    }

    private static function oils(): array
    {
        $o = fn (string $name, string $brand, int $mrp, int $price, string $type, string $visc, array $extra = []) => self::part($name, $brand, 'engine-oil', $mrp, $price, 'universal', 'oil', 'oil', $extra + [
            'vehicle_type' => $type, 'attrs' => ['Viscosity' => $visc], 'specs' => array_merge(['Viscosity' => $visc], $extra['specs'] ?? []), 'tags' => ['engine oil', strtolower($visc), strtolower($brand)],
        ]);

        return [
            $o('Motul 7100 4T 10W-50 Fully Synthetic (1L)', 'Motul', 1099, 949, 'motorcycle', '10W-50', ['featured' => true, 'specs' => ['Base' => '100% synthetic ester', 'API' => 'SN', 'JASO' => 'MA2', 'Volume' => '1 L']]),
            $o('Motul 300V Factory Line 10W-40 (1L)', 'Motul', 1799, 1599, 'motorcycle', '10W-40', ['specs' => ['Base' => 'Double ester', 'Volume' => '1 L']]),
            $o('Motul 3000 4T 20W-50 Mineral (1L)', 'Motul', 449, 389, 'motorcycle', '20W-50', ['specs' => ['Base' => 'Mineral', 'Volume' => '1 L']]),
            $o('Castrol Power1 Ultimate 10W-50 (1L)', 'Castrol', 899, 749, 'motorcycle', '10W-50', ['specs' => ['Base' => 'Fully synthetic', 'Volume' => '1 L']]),
            $o('Castrol EDGE 5W-30 Fully Synthetic (3.5L)', 'Castrol', 3999, 3399, 'car', '5W-30', ['featured' => true, 'specs' => ['API' => 'SP', 'Volume' => '3.5 L', 'Suitable for' => 'Petrol & diesel']]),
            $o('Castrol Magnatec 5W-40 Diesel (5L)', 'Castrol', 3599, 2999, 'car', '5W-40', ['specs' => ['ACEA' => 'C3', 'Volume' => '5 L']]),
            $o('Mobil 1 0W-40 Advanced Full Synthetic (4L)', 'Mobil 1', 5299, 4599, 'car', '0W-40', ['specs' => ['Approvals' => 'MB 229.5, VW 502/505', 'Volume' => '4 L']]),
            $o('Liqui Moly Molygen 5W-30 (4L)', 'Liqui Moly', 5499, 4699, 'car', '5W-30', ['specs' => ['Technology' => 'Molygen friction reduction', 'Volume' => '4 L']]),
            $o('Castrol GTX 15W-40 Diesel (7.5L)', 'Castrol', 3299, 2799, 'car', '15W-40', ['specs' => ['Volume' => '7.5 L', 'Suitable for' => 'Diesel SUVs']]),
            self::part('Motul C2 Chain Lube Road (400ml)', 'Motul', 'fluids-lubricants', 699, 599, 'universal', 'spray', 'oil', ['vehicle_type' => 'motorcycle', 'featured' => true, 'specs' => ['Volume' => '400 ml', 'Suitable for' => 'O/X-ring chains'], 'tags' => ['chain lube', 'chain spray', 'maintenance']]),
            self::part('Motul C1 Chain Clean (400ml)', 'Motul', 'fluids-lubricants', 649, 549, 'universal', 'spray', 'oil', ['vehicle_type' => 'motorcycle', 'tags' => ['chain cleaner', 'maintenance']]),
            self::part('Bosch DOT 4 Brake Fluid (500ml)', 'Bosch', 'fluids-lubricants', 499, 399, 'universal', 'drop', 'oil', ['specs' => ['Standard' => 'DOT 4', 'Boiling point' => '265°C'], 'tags' => ['brake fluid', 'dot 4']]),
            self::part('Liqui Moly Radiator Coolant Concentrate (1L)', 'Liqui Moly', 'fluids-lubricants', 899, 749, 'universal', 'drop', 'care', ['vehicle_type' => 'car', 'specs' => ['Type' => 'OAT long life', 'Mix ratio' => '1:1'], 'tags' => ['coolant', 'antifreeze']]),
            self::part('Castrol Axle EPX 80W-90 Gear Oil (1L)', 'Castrol', 'fluids-lubricants', 599, 499, 'universal', 'oil', 'oil', ['vehicle_type' => 'car', 'specs' => ['API' => 'GL-5'], 'tags' => ['gear oil', 'differential']]),
            self::part('Liqui Moly Engine Flush (300ml)', 'Liqui Moly', 'fluids-lubricants', 799, 649, 'universal', 'drop', 'oil', ['tags' => ['engine flush', 'additive']]),
            self::part('3M Windshield Washer Concentrate (500ml)', '3M', 'fluids-lubricants', 399, 299, 'universal', 'drop', 'care', ['vehicle_type' => 'car', 'tags' => ['washer fluid', 'windshield']]),
        ];
    }

    private static function part(string $name, string $brand, string $category, int $mrp, int $price, array|string $fits, string $icon, string $palette, array $extra = []): array
    {
        if (isset($extra['fits'])) {
            $fits = $extra['fits'];
            unset($extra['fits']);
        }

        return array_merge([
            'name' => $name,
            'brand' => $brand,
            'category' => $category,
            'mrp' => $mrp,
            'price' => $price,
            'fits' => $fits,
            'icon' => $icon,
            'palette' => $palette,
            'vehicle_type' => $extra['vehicle_type'] ?? (in_array($category, ['brake-parts', 'chain-sprocket', 'bike-exhaust', 'bike-lighting', 'bike-suspension', 'bike-electrical', 'performance', 'touring'], true) ? 'motorcycle' : 'car'),
        ], $extra);
    }
}
