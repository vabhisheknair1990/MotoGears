<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Models\VehicleVariant;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public const CUSTOMERS = [
        ['Rahul Sharma', 'Bengaluru', 'Karnataka', '560034', 'Koramangala 5th Block, 12th Main'],
        ['Priya Nair', 'Kochi', 'Kerala', '682020', 'Panampilly Nagar, 3rd Cross'],
        ['Arjun Reddy', 'Hyderabad', 'Telangana', '500081', 'Madhapur, Ayyappa Society'],
        ['Sneha Patil', 'Pune', 'Maharashtra', '411045', 'Baner Road, Pallod Farms'],
        ['Vikram Singh', 'Jaipur', 'Rajasthan', '302021', 'Vaishali Nagar, Sector 4'],
        ['Ananya Iyer', 'Chennai', 'Tamil Nadu', '600041', 'Thiruvanmiyur, Kamaraj Nagar'],
        ['Karan Mehta', 'Mumbai', 'Maharashtra', '400053', 'Andheri West, Lokhandwala'],
        ['Divya Menon', 'Thiruvananthapuram', 'Kerala', '695010', 'Kowdiar, Jawahar Nagar'],
        ['Rohit Verma', 'Gurugram', 'Haryana', '122002', 'DLF Phase 3, U Block'],
        ['Meera Joshi', 'Ahmedabad', 'Gujarat', '380015', 'Satellite, Jodhpur Cross Road'],
        ['Aditya Kulkarni', 'Nagpur', 'Maharashtra', '440010', 'Dharampeth, WHC Road'],
        ['Fatima Khan', 'Lucknow', 'Uttar Pradesh', '226010', 'Gomti Nagar, Vipul Khand'],
        ['Siddharth Rao', 'Mysuru', 'Karnataka', '570009', 'Kuvempunagar, 2nd Stage'],
        ['Neha Gupta', 'New Delhi', 'Delhi', '110017', 'Malviya Nagar, Shivalik Road'],
        ['Manish Agarwal', 'Kolkata', 'West Bengal', '700091', 'Salt Lake, Sector V'],
        ['Kavya Hegde', 'Mangaluru', 'Karnataka', '575003', 'Kadri, Bejai Main Road'],
        ['Harpreet Kaur', 'Chandigarh', 'Chandigarh', '160022', 'Sector 22-C'],
        ['Suresh Babu', 'Coimbatore', 'Tamil Nadu', '641018', 'RS Puram, DB Road'],
        ['Ishaan Chatterjee', 'Bhubaneswar', 'Odisha', '751007', 'Saheed Nagar'],
        ['Pooja Deshmukh', 'Nashik', 'Maharashtra', '422005', 'College Road'],
        ['Nikhil Bansal', 'Indore', 'Madhya Pradesh', '452010', 'Vijay Nagar, Scheme 54'],
        ['Lakshmi Pillai', 'Kozhikode', 'Kerala', '673006', 'Nadakkavu, Kannur Road'],
        ['Aman Tiwari', 'Bhopal', 'Madhya Pradesh', '462016', 'Arera Colony, E-7'],
        ['Tanvi Shah', 'Surat', 'Gujarat', '395007', 'Vesu, VIP Road'],
    ];

    public function run(): void
    {
        mt_srand(99);
        $password = \Illuminate\Support\Facades\Hash::make('password');

        $staff = [
            ['Store Owner', 'admin@example.com', Role::SUPER_ADMIN],
            ['Anita Rao', 'manager@example.com', Role::ADMIN],
            ['Ravi Kumar', 'catalog@example.com', Role::CATALOG_MANAGER],
            ['Deepa Nair', 'orders@example.com', Role::ORDER_MANAGER],
            ['Sunil Das', 'inventory@example.com', Role::INVENTORY_MANAGER],
            ['Maya Fernandes', 'content@example.com', Role::CONTENT_MANAGER],
        ];
        foreach ($staff as [$name, $email, $role]) {
            $u = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'phone' => '+91 98450 '.mt_rand(10000, 99999)]);
            $u->forceFill(['email_verified_at' => now(), 'is_active' => true])->save();
            $u->roles()->sync(Role::where('name', $role)->pluck('id'));
        }

        $variants = VehicleVariant::with('model')->get();
        $customers = array_merge([['Aarav Menon', 'Bengaluru', 'Karnataka', '560038', 'Indiranagar, 100 Feet Road', 'customer@example.com']], self::CUSTOMERS);

        foreach ($customers as $i => $c) {
            [$name, $city, $state, $pin, $street] = $c;
            $email = $c[5] ?? strtolower(str_replace(' ', '.', $name)).'@example.com';
            $phone = '+91 9'.mt_rand(100000000, 999999999);
            $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => $password, 'phone' => $phone, 'marketing_opt_in' => (bool) mt_rand(0, 1)]);
            $user->forceFill([
                'email_verified_at' => now(),
                'is_active' => true,
                'created_at' => now()->subDays(mt_rand(20, 300)),
                'last_login_at' => now()->subDays(mt_rand(0, 20)),
            ])->save();
            $user->roles()->sync(Role::where('name', Role::CUSTOMER)->pluck('id'));
            Wishlist::firstOrCreate(['user_id' => $user->id]);

            if (! $user->addresses()->exists()) {
                $user->addresses()->create([
                    'label' => 'Home', 'name' => $name, 'phone' => $phone, 'line1' => 'Flat '.mt_rand(101, 1204).', '.$street,
                    'line2' => null, 'landmark' => 'Near '.['City Mall', 'Metro Station', 'Govt. School', 'Axis Bank', 'Post Office'][mt_rand(0, 4)],
                    'city' => $city, 'state' => $state, 'postal_code' => $pin, 'country' => 'IN', 'is_default' => true,
                ]);
                if ($i % 3 === 0) {
                    $user->addresses()->create([
                        'label' => 'Work', 'name' => $name, 'phone' => $phone, 'line1' => 'Level '.mt_rand(2, 12).', Tech Park Tower '.chr(65 + $i % 5),
                        'line2' => 'Outer Ring Road', 'city' => $city, 'state' => $state, 'postal_code' => $pin, 'country' => 'IN', 'is_default' => false,
                    ]);
                }
            }

            if (! $user->vehicles()->exists()) {
                $garage = $i === 0
                    ? [$variants->first(fn ($v) => $v->model->name === 'Thar' && $v->name === '2.0 Petrol AT'), $variants->first(fn ($v) => $v->model->name === 'Classic 350' && $v->name === 'Signals')]
                    : $variants->random(mt_rand(0, 2))->all();
                foreach (array_values(array_filter($garage)) as $j => $variant) {
                    $user->vehicles()->create([
                        'vehicle_variant_id' => $variant->id,
                        'year' => $i === 0 ? ($j === 0 ? 2024 : 2023) : mt_rand($variant->year_from, $variant->year_to ?? (int) date('Y')),
                        'nickname' => $i === 0 ? ($j === 0 ? 'Weekend Thar' : 'Daily ride') : null,
                        'registration_number' => $i === 0 ? ($j === 0 ? 'KA 03 MX 2024' : 'KA 01 EZ 3350') : null,
                        'is_default' => $j === 0,
                    ]);
                }
            }
        }
    }
}
