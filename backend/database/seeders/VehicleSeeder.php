<?php

namespace Database\Seeders;

use App\Models\VehicleManufacturer;
use App\Models\VehicleModel;
use Database\Seeders\Support\Art;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VehicleSeeder extends Seeder
{
    /**
     * manufacturer => [type, country, models => [model => [type, body, variants => [name, from, to, engine, fuel, transmission, cc]]]]
     */
    public const DATA = [
        'Mahindra' => ['car', 'India', [
            'Thar' => ['car', 'SUV', [
                ['2.0 Petrol MT', 2020, null, '2.0L mStallion Turbo', 'Petrol', 'MT', 1997],
                ['2.0 Petrol AT', 2020, null, '2.0L mStallion Turbo', 'Petrol', 'AT', 1997],
                ['2.2 Diesel MT', 2020, null, '2.2L mHawk', 'Diesel', 'MT', 2184],
                ['2.2 Diesel AT', 2020, null, '2.2L mHawk', 'Diesel', 'AT', 2184],
                ['1.5 Diesel RWD MT', 2023, null, '1.5L D117 CRDe', 'Diesel', 'MT', 1497],
            ]],
            'Scorpio-N' => ['car', 'SUV', [
                ['2.0 Petrol MT', 2022, null, '2.0L mStallion', 'Petrol', 'MT', 1997],
                ['2.0 Petrol AT', 2022, null, '2.0L mStallion', 'Petrol', 'AT', 1997],
                ['2.2 Diesel MT 4XPLOR', 2022, null, '2.2L mHawk', 'Diesel', 'MT', 2184],
                ['2.2 Diesel AT', 2022, null, '2.2L mHawk', 'Diesel', 'AT', 2184],
            ]],
            'XUV700' => ['car', 'SUV', [
                ['2.0 Petrol AT', 2021, null, '2.0L mStallion', 'Petrol', 'AT', 1997],
                ['2.2 Diesel MT', 2021, null, '2.2L mHawk', 'Diesel', 'MT', 2184],
                ['2.2 Diesel AT AWD', 2021, null, '2.2L mHawk', 'Diesel', 'AT', 2184],
            ]],
        ]],
        'Hyundai' => ['car', 'South Korea', [
            'Creta' => ['car', 'SUV', [
                ['1.5 Petrol MT', 2020, null, '1.5L MPi', 'Petrol', 'MT', 1497],
                ['1.5 Petrol IVT', 2020, null, '1.5L MPi', 'Petrol', 'CVT', 1497],
                ['1.5 Diesel MT', 2020, null, '1.5L U2 CRDi', 'Diesel', 'MT', 1493],
                ['1.5 Diesel AT', 2020, null, '1.5L U2 CRDi', 'Diesel', 'AT', 1493],
                ['1.5 Turbo DCT', 2024, null, '1.5L T-GDi', 'Petrol', 'DCT', 1482],
            ]],
            'Venue' => ['car', 'Compact SUV', [
                ['1.2 Petrol MT', 2019, null, '1.2L Kappa', 'Petrol', 'MT', 1197],
                ['1.0 Turbo DCT', 2019, null, '1.0L Turbo GDi', 'Petrol', 'DCT', 998],
                ['1.5 Diesel MT', 2020, null, '1.5L CRDi', 'Diesel', 'MT', 1493],
            ]],
            'i20' => ['car', 'Hatchback', [
                ['1.2 Petrol MT', 2020, null, '1.2L Kappa', 'Petrol', 'MT', 1197],
                ['1.2 Petrol IVT', 2020, null, '1.2L Kappa', 'Petrol', 'CVT', 1197],
                ['1.0 Turbo DCT', 2020, 2023, '1.0L Turbo GDi', 'Petrol', 'DCT', 998],
            ]],
        ]],
        'Tata' => ['car', 'India', [
            'Nexon' => ['car', 'Compact SUV', [
                ['1.2 Turbo Petrol MT', 2017, null, '1.2L Revotron', 'Petrol', 'MT', 1199],
                ['1.2 Turbo Petrol AMT', 2019, null, '1.2L Revotron', 'Petrol', 'AMT', 1199],
                ['1.5 Diesel MT', 2017, null, '1.5L Revotorq', 'Diesel', 'MT', 1497],
                ['EV Long Range', 2022, null, 'Permanent Magnet Synchronous', 'Electric', 'Single-speed', null],
            ]],
            'Harrier' => ['car', 'SUV', [
                ['2.0 Diesel MT', 2019, null, '2.0L Kryotec', 'Diesel', 'MT', 1956],
                ['2.0 Diesel AT', 2020, null, '2.0L Kryotec', 'Diesel', 'AT', 1956],
            ]],
            'Punch' => ['car', 'Micro SUV', [
                ['1.2 Petrol MT', 2021, null, '1.2L Revotron', 'Petrol', 'MT', 1199],
                ['1.2 Petrol AMT', 2021, null, '1.2L Revotron', 'Petrol', 'AMT', 1199],
                ['1.2 iCNG', 2023, null, '1.2L Revotron', 'CNG', 'MT', 1199],
            ]],
        ]],
        'Toyota' => ['car', 'Japan', [
            'Fortuner' => ['car', 'SUV', [
                ['2.7 Petrol AT 4x2', 2016, null, '2.7L 2TR-FE', 'Petrol', 'AT', 2694],
                ['2.8 Diesel MT 4x2', 2016, null, '2.8L 1GD-FTV', 'Diesel', 'MT', 2755],
                ['2.8 Diesel AT 4x4', 2016, null, '2.8L 1GD-FTV', 'Diesel', 'AT', 2755],
            ]],
            'Innova Crysta' => ['car', 'MPV', [
                ['2.4 Diesel MT', 2016, null, '2.4L 2GD-FTV', 'Diesel', 'MT', 2393],
                ['2.7 Petrol AT', 2016, 2022, '2.7L 2TR-FE', 'Petrol', 'AT', 2694],
            ]],
            'Innova Hycross' => ['car', 'MPV', [
                ['2.0 Petrol CVT', 2022, null, '2.0L M20A', 'Petrol', 'CVT', 1987],
                ['2.0 Strong Hybrid e-CVT', 2022, null, '2.0L M20A-FXS Hybrid', 'Hybrid', 'CVT', 1987],
            ]],
        ]],
        'Maruti Suzuki' => ['car', 'India', [
            'Swift' => ['car', 'Hatchback', [
                ['1.2 Petrol MT', 2018, 2024, '1.2L K12N DualJet', 'Petrol', 'MT', 1197],
                ['1.2 Petrol AMT', 2018, 2024, '1.2L K12N DualJet', 'Petrol', 'AMT', 1197],
                ['1.2 Z-Series MT', 2024, null, '1.2L Z12E', 'Petrol', 'MT', 1197],
            ]],
            'Baleno' => ['car', 'Hatchback', [
                ['1.2 Petrol MT', 2022, null, '1.2L K12N DualJet', 'Petrol', 'MT', 1197],
                ['1.2 Petrol AMT', 2022, null, '1.2L K12N DualJet', 'Petrol', 'AMT', 1197],
                ['1.2 CNG', 2022, null, '1.2L K12N', 'CNG', 'MT', 1197],
            ]],
            'Brezza' => ['car', 'Compact SUV', [
                ['1.5 Petrol MT', 2022, null, '1.5L K15C', 'Petrol', 'MT', 1462],
                ['1.5 Petrol AT', 2022, null, '1.5L K15C', 'Petrol', 'AT', 1462],
            ]],
        ]],
        'Kia' => ['car', 'South Korea', [
            'Seltos' => ['car', 'SUV', [
                ['1.5 Petrol MT', 2019, null, '1.5L Smartstream', 'Petrol', 'MT', 1497],
                ['1.5 Turbo DCT', 2023, null, '1.5L T-GDi', 'Petrol', 'DCT', 1482],
                ['1.5 Diesel AT', 2019, null, '1.5L CRDi VGT', 'Diesel', 'AT', 1493],
            ]],
            'Sonet' => ['car', 'Compact SUV', [
                ['1.2 Petrol MT', 2020, null, '1.2L Smartstream', 'Petrol', 'MT', 1197],
                ['1.0 Turbo iMT', 2020, null, '1.0L T-GDi', 'Petrol', 'MT', 998],
            ]],
        ]],
        'Honda' => ['both', 'Japan', [
            'City' => ['car', 'Sedan', [
                ['1.5 i-VTEC MT', 2020, null, '1.5L i-VTEC', 'Petrol', 'MT', 1498],
                ['1.5 i-VTEC CVT', 2020, null, '1.5L i-VTEC', 'Petrol', 'CVT', 1498],
                ['e:HEV Hybrid', 2022, null, '1.5L Atkinson Hybrid', 'Hybrid', 'CVT', 1498],
            ]],
            'CB350' => ['motorcycle', 'Cruiser', [
                ['H\'ness DLX', 2020, null, '348cc Air-cooled', 'Petrol', 'MT', 348],
                ['RS', 2021, null, '348cc Air-cooled', 'Petrol', 'MT', 348],
            ]],
            'Activa 6G' => ['motorcycle', 'Scooter', [
                ['Standard', 2020, null, '109.51cc Fan-cooled', 'Petrol', 'CVT', 110],
                ['DLX', 2020, null, '109.51cc Fan-cooled', 'Petrol', 'CVT', 110],
            ]],
        ]],
        'Royal Enfield' => ['motorcycle', 'India', [
            'Classic 350' => ['motorcycle', 'Cruiser', [
                ['Redditch', 2021, null, '349cc J-series', 'Petrol', 'MT', 349],
                ['Signals', 2021, null, '349cc J-series', 'Petrol', 'MT', 349],
                ['Chrome', 2021, null, '349cc J-series', 'Petrol', 'MT', 349],
                ['UCE (Previous Gen)', 2009, 2021, '346cc UCE', 'Petrol', 'MT', 346],
            ]],
            'Himalayan' => ['motorcycle', 'Adventure', [
                ['411 BS6', 2016, 2023, '411cc LS410', 'Petrol', 'MT', 411],
                ['450', 2023, null, '452cc Sherpa liquid-cooled', 'Petrol', 'MT', 452],
            ]],
            'Hunter 350' => ['motorcycle', 'Roadster', [
                ['Retro', 2022, null, '349cc J-series', 'Petrol', 'MT', 349],
                ['Metro', 2022, null, '349cc J-series', 'Petrol', 'MT', 349],
            ]],
            'Bullet 350' => ['motorcycle', 'Cruiser', [
                ['Standard', 2023, null, '349cc J-series', 'Petrol', 'MT', 349],
            ]],
            'Interceptor 650' => ['motorcycle', 'Roadster', [
                ['Standard', 2018, null, '648cc Parallel Twin', 'Petrol', 'MT', 648],
                ['Chrome', 2018, null, '648cc Parallel Twin', 'Petrol', 'MT', 648],
            ]],
        ]],
        'KTM' => ['motorcycle', 'Austria', [
            'Duke 200' => ['motorcycle', 'Naked', [['Standard', 2012, null, '199.5cc liquid-cooled', 'Petrol', 'MT', 200]]],
            'Duke 390' => ['motorcycle', 'Naked', [
                ['Gen 2', 2017, 2023, '373cc liquid-cooled', 'Petrol', 'MT', 373],
                ['Gen 3', 2024, null, '399cc LC4c', 'Petrol', 'MT', 399],
            ]],
            'Adventure 390' => ['motorcycle', 'Adventure', [['Standard', 2020, null, '373cc liquid-cooled', 'Petrol', 'MT', 373]]],
            'RC 390' => ['motorcycle', 'Sport', [['Standard', 2022, null, '373cc liquid-cooled', 'Petrol', 'MT', 373]]],
        ]],
        'Bajaj' => ['motorcycle', 'India', [
            'Pulsar NS200' => ['motorcycle', 'Naked', [['Standard', 2012, null, '199.5cc liquid-cooled', 'Petrol', 'MT', 200]]],
            'Dominar 400' => ['motorcycle', 'Tourer', [['Standard', 2017, null, '373.3cc liquid-cooled', 'Petrol', 'MT', 373]]],
            'Pulsar N160' => ['motorcycle', 'Naked', [['Dual Channel ABS', 2022, null, '164.82cc oil-cooled', 'Petrol', 'MT', 165]]],
        ]],
        'TVS' => ['motorcycle', 'India', [
            'Apache RTR 160 4V' => ['motorcycle', 'Naked', [['Standard', 2018, null, '159.7cc oil-cooled', 'Petrol', 'MT', 160]]],
            'Apache RR 310' => ['motorcycle', 'Sport', [['Standard', 2017, null, '312.2cc liquid-cooled', 'Petrol', 'MT', 312]]],
            'Jupiter' => ['motorcycle', 'Scooter', [['110', 2019, null, '109.7cc', 'Petrol', 'CVT', 110]]],
        ]],
        'Yamaha' => ['motorcycle', 'Japan', [
            'R15 V4' => ['motorcycle', 'Sport', [['Standard', 2021, null, '155cc VVA liquid-cooled', 'Petrol', 'MT', 155]]],
            'MT-15 V2' => ['motorcycle', 'Naked', [['Standard', 2022, null, '155cc VVA liquid-cooled', 'Petrol', 'MT', 155]]],
            'FZ-S FI V4' => ['motorcycle', 'Commuter', [['Standard', 2021, null, '149cc air-cooled', 'Petrol', 'MT', 149]]],
        ]],
        'Hero' => ['motorcycle', 'India', [
            'Splendor Plus' => ['motorcycle', 'Commuter', [['Standard', 2020, null, '97.2cc air-cooled', 'Petrol', 'MT', 97]]],
            'Xpulse 200 4V' => ['motorcycle', 'Adventure', [['Standard', 2021, null, '199.6cc oil-cooled', 'Petrol', 'MT', 200]]],
        ]],
    ];

    public function run(): void
    {
        $i = 0;
        foreach (self::DATA as $make => [$type, $country, $models]) {
            $slug = Str::slug($make);
            $m = VehicleManufacturer::updateOrCreate(['slug' => $slug], [
                'name' => $make, 'vehicle_type' => $type, 'country' => $country, 'is_active' => true, 'sort_order' => $i++,
                'logo_path' => Art::wordmark("manufacturers/{$slug}.svg", $make, '#111827', $type === 'motorcycle' ? '#f97316' : '#2563eb'),
            ]);
            foreach ($models as $modelName => [$modelType, $body, $variants]) {
                $model = VehicleModel::updateOrCreate(
                    ['vehicle_manufacturer_id' => $m->id, 'slug' => Str::slug($modelName)],
                    ['name' => $modelName, 'vehicle_type' => $modelType, 'body_type' => $body, 'is_active' => true],
                );
                foreach ($variants as [$name, $from, $to, $engine, $fuel, $trans, $cc]) {
                    $model->variants()->updateOrCreate(['name' => $name], [
                        'year_from' => $from, 'year_to' => $to, 'engine' => $engine, 'fuel_type' => $fuel,
                        'transmission' => $trans, 'displacement_cc' => $cc, 'is_active' => true,
                    ]);
                }
            }
        }
    }
}
