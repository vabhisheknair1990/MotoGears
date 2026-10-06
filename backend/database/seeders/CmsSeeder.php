<?php

namespace Database\Seeders;

use App\Models\Banner;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\ContactMessage;
use App\Models\Faq;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\SearchTerm;
use App\Models\Testimonial;
use App\Models\User;
use Database\Seeders\Support\Art;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $this->banners();
        $this->pages();
        $this->faqs();
        $this->blog();
        $this->misc();
    }

    private function banners(): void
    {
        $banners = [
            ['hero', 'Find the Right Parts for Your Vehicle', 'Over 170 genuine parts and accessories with guaranteed fitment for 40+ Indian cars and bikes.', 'GENUINE PARTS • PERFECT FIT', 'Shop now', '/shop', 'car', 'default'],
            ['hero', 'Brake Week: 15% off Brembo & Bosch', 'Upgrade your stopping power. Use code BRAKES15 on brake pads, discs and shoes.', 'LIMITED TIME', 'Shop brakes', '/category/brake-system', 'disc', 'brake'],
            ['hero', 'Built for the Long Ride', 'Saddle bags, crash guards and auxiliary lights for your next Himalayan adventure. 20% off with RIDE20.', 'TOURING SEASON', 'Explore touring', '/category/touring', 'bike', 'touring'],
            ['promo', 'Free Shipping on Every Order', 'Use code FREESHIP at checkout — no minimum.', 'OFFER', 'Start shopping', '/shop', 'car', 'exterior'],
            ['promo', 'Motul Engine Oils', 'Fully synthetic protection for bikes and cars.', 'NEW STOCK', 'Shop oils', '/category/engine-oil', 'oil', 'oil'],
            ['offer', 'LED Lighting Upgrades', 'Brighter nights with Philips, Hella & Osram.', 'UP TO 30% OFF', 'Shop lighting', '/category/lighting', 'headlight', 'light'],
        ];
        foreach ($banners as $i => [$placement, $title, $sub, $eyebrow, $cta, $url, $icon, $palette]) {
            $slug = Str::slug($title);
            Banner::updateOrCreate(['title' => $title], [
                'subtitle' => $sub, 'eyebrow' => $eyebrow, 'cta_label' => $cta, 'cta_url' => $url, 'placement' => $placement, 'theme' => 'dark',
                'desktop_image_path' => Art::banner("banners/{$slug}.svg", $title, $icon, $palette),
                'mobile_image_path' => Art::banner("banners/{$slug}-mobile.svg", $title, $icon, $palette, true),
                'is_active' => true, 'sort_order' => $i, 'starts_at' => now()->subDays(30), 'ends_at' => now()->addMonths(6),
            ]);
        }
    }

    private function pages(): void
    {
        $pages = [
            'about' => ['About MotoGears', '<h2>Genuine parts. Perfect fit.</h2><p>MotoGears was started in Bengaluru in 2019 by a group of mechanics and riders who were tired of guessing whether a part would fit. Today we stock thousands of genuine parts and accessories for Indian cars, SUVs and motorcycles from 28 of the world\'s most trusted brands.</p><h3>What makes us different</h3><ul><li><strong>Fitment first.</strong> Every product is mapped to the exact make, model, variant and year it fits.</li><li><strong>Only genuine stock.</strong> We buy directly from brands and authorised distributors.</li><li><strong>Real humans.</strong> Our support team includes trained technicians who can help you choose the right part.</li></ul><p>Whether you are servicing a daily commuter or building a weekend Thar, we are here to help you ride and drive with confidence.</p>'],
            'shipping-policy' => ['Shipping Policy', '<p>We ship across India from our Bengaluru and Gurugram warehouses.</p><ul><li><strong>Standard delivery:</strong> 4–6 business days. Free on orders above ₹2,999; otherwise ₹100.</li><li><strong>Express delivery:</strong> 1–2 business days in metro cities for ₹250.</li></ul><p>You will receive a tracking number by email and in your account as soon as your order ships.</p>'],
            'returns-policy' => ['Returns & Refunds', '<p>If a part does not fit the vehicle you selected at purchase, we will collect it free of charge within 7 days of delivery and refund you in full.</p><p>Other returns are accepted within 7 days for unused products in original packaging. Electrical items, fluids and opened oils cannot be returned for hygiene and safety reasons.</p><p>Refunds are issued to the original payment method within 5–7 business days of the return being received.</p>'],
            'privacy-policy' => ['Privacy Policy', '<p>We collect only the information needed to process your orders and improve your experience: your name, contact details, addresses, saved vehicles and order history. We never sell your data.</p><p>Payment card details are never stored on our servers. You can update or delete your information at any time from your account.</p>'],
            'terms' => ['Terms & Conditions', '<p>By using MotoGears you agree to these terms. Prices include applicable discounts and are exclusive of GST, which is shown separately at checkout. We reserve the right to cancel orders in case of pricing errors or stock unavailability, with a full refund.</p>'],
        ];
        foreach ($pages as $slug => [$title, $content]) {
            Page::updateOrCreate(['slug' => $slug], ['title' => $title, 'content' => $content, 'meta_title' => $title.' | MotoGears', 'meta_description' => Str::limit(strip_tags($content), 150), 'is_active' => true]);
        }
    }

    private function faqs(): void
    {
        $faqs = [
            ['Orders', 'How do I know a part will fit my vehicle?', 'Select your vehicle using the Make → Model → Year → Variant selector. Products that fit are marked with a green "Fits your vehicle" badge, and every product page lists all compatible vehicles.'],
            ['Orders', 'Can I cancel my order?', 'Yes. You can cancel from My Orders any time before the order is packed. Prepaid orders are refunded automatically to the original payment method.'],
            ['Orders', 'How do I track my order?', 'Open My Orders in your account to see the live timeline and courier tracking number once your order ships.'],
            ['Payments', 'Which payment methods do you accept?', 'Cash on Delivery (up to ₹50,000), cards and UPI. This demo store simulates card and UPI payments — no real money is charged.'],
            ['Payments', 'Is Cash on Delivery available everywhere?', 'COD is available across most serviceable PIN codes for orders up to ₹50,000.'],
            ['Payments', 'Do I get a GST invoice?', 'Yes, every order includes a GST invoice you can view, print or download from your order page.'],
            ['Shipping', 'How much does shipping cost?', 'Standard delivery is free above ₹2,999, otherwise ₹100. Express delivery costs ₹250.'],
            ['Shipping', 'How long does delivery take?', 'Standard delivery takes 4–6 business days; express delivery takes 1–2 business days in metro cities.'],
            ['Returns', 'What if the part does not fit?', 'If you selected your vehicle and the part does not fit, we pick it up free and refund you in full within 7 days of delivery.'],
            ['Returns', 'Which items cannot be returned?', 'Opened oils and fluids, and electrical items that have been installed cannot be returned for safety reasons.'],
            ['Products', 'Are your products genuine?', 'Absolutely. We source only from brands and their authorised distributors, and every product carries the manufacturer warranty.'],
            ['Products', 'Do you offer installation?', 'Batteries include free installation at partner garages in 30+ cities. For other parts we recommend a qualified mechanic; installation guidance is provided on each product page.'],
        ];
        foreach ($faqs as $i => [$cat, $q, $a]) {
            Faq::updateOrCreate(['question' => $q], ['category' => $cat, 'answer' => $a, 'is_active' => true, 'sort_order' => $i]);
        }
    }

    private function blog(): void
    {
        $author = User::where('email', 'content@example.com')->first();
        $cats = [];
        foreach (['Maintenance', 'Buying Guides', 'Riding & Touring', 'News'] as $name) {
            $cats[$name] = BlogCategory::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'description' => "{$name} articles from the MotoGears garage."]);
        }
        $posts = [
            ['When Should You Replace Your Brake Pads?', 'Maintenance', 'disc', 'brake', ['brakes', 'safety', 'maintenance'], 14,
                '<p>Brake pads are the single most important wear item on your car or bike, yet most of us only think about them when we hear a squeal. Here is how to tell when it is time for a new set.</p><h2>1. Listen for the wear indicator</h2><p>Most modern pads, including Bosch and Brembo, have an acoustic wear indicator — a small metal tab that squeals against the disc when the friction material is nearly gone.</p><h2>2. Check the thickness</h2><p>Look through the wheel spokes. If the pad material is thinner than 3 mm, replace it. On motorcycles, most pads have a groove that disappears when they are worn.</p><h2>3. Watch for vibration</h2><p>A pulsing brake pedal usually means warped discs. Replace discs and pads together for the best result.</p><h2>How often?</h2><p>In Indian city traffic, front pads typically last 20,000–35,000 km on cars and 10,000–15,000 km on motorcycles.</p>'],
            ['LED vs Halogen Headlights: Which Should You Choose?', 'Buying Guides', 'headlight', 'light', ['lighting', 'led', 'buying guide'], 30,
                '<p>Upgrading your headlights is one of the most effective safety improvements you can make. But should you go for LED or stick with a premium halogen like Philips Xtreme Vision?</p><h2>Brightness and colour</h2><p>LEDs produce whiter light (6000K) that makes road markings pop. Premium halogens are warmer (3000–3700K) but cut through rain and fog better.</p><h2>Legality and beam pattern</h2><p>Choose kits designed for your headlamp housing so the beam pattern stays correct. A badly aimed LED blinds oncoming traffic.</p><h2>Our pick</h2><p>For projector headlamps, a quality LED kit is a big upgrade. For reflector headlamps, a premium halogen often gives a cleaner beam.</p>'],
            ['The Ultimate Himalayan Touring Checklist', 'Riding & Touring', 'bike', 'touring', ['touring', 'himalayan', 'motorcycle'], 45,
                '<p>Planning a ride to Ladakh or Spiti? Here is the gear our team never leaves without.</p><ul><li><strong>Luggage:</strong> waterproof saddle bags and a tank bag for essentials.</li><li><strong>Protection:</strong> crash guards and a sump guard — rocks are unforgiving.</li><li><strong>Lighting:</strong> auxiliary LEDs for tunnels and late arrivals.</li><li><strong>Maintenance kit:</strong> chain lube, spare clutch and brake cables, puncture kit and a compact tyre inflator.</li></ul><p>Service your bike two weeks before departure so any issues show up close to home.</p>'],
            ['How Often Should You Change Engine Oil?', 'Maintenance', 'oil', 'oil', ['engine oil', 'maintenance'], 60,
                '<p>The old "every 5,000 km" rule is outdated for many modern engines, but Indian driving conditions are tough on oil.</p><h2>Cars</h2><p>Fully synthetic oils like Castrol EDGE or Mobil 1 typically last 10,000 km or one year. If you drive mostly in stop-and-go traffic, halve that interval.</p><h2>Motorcycles</h2><p>Motorcycle engines share oil with the gearbox and clutch, so change oil every 3,000–5,000 km with a JASO MA2 oil such as Motul 7100.</p>'],
            ['5 Upgrades Every New Mahindra Thar Owner Should Consider', 'Buying Guides', 'car', 'default', ['thar', 'upgrades', 'off-road'], 75,
                '<p>The Thar is a fantastic platform straight from the showroom, but a few smart upgrades make it even better.</p><ol><li><strong>LED headlight kit</strong> — the stock halogens are weak on dark highways.</li><li><strong>High-flow air filter</strong> — a K&N filter improves throttle response and is washable.</li><li><strong>Ceramic brake pads</strong> — less dust and better bite with bigger tyres.</li><li><strong>Seat covers</strong> — protect the upholstery from mud and water.</li><li><strong>Tyre inflator</strong> — essential if you air down off-road.</li></ol>'],
            ['Monsoon Car Care: A Complete Guide', 'Maintenance', 'wiper', 'exterior', ['monsoon', 'car care'], 110,
                '<p>Rain brings waterlogged roads, poor visibility and rust. Prepare your car with this checklist.</p><ul><li>Replace wiper blades every 6–12 months.</li><li>Check tyre tread depth — at least 3 mm for wet roads.</li><li>Clean drain holes in doors and the sunroof.</li><li>Apply wax to protect paint from acid rain.</li><li>Test all lights, including fog lamps.</li></ul>'],
        ];
        foreach ($posts as [$title, $cat, $icon, $palette, $tags, $daysAgo, $content]) {
            $slug = Str::slug($title);
            $post = BlogPost::updateOrCreate(['slug' => $slug], [
                'title' => $title, 'blog_category_id' => $cats[$cat]->id, 'author_id' => $author?->id,
                'excerpt' => Str::limit(strip_tags($content), 170), 'content' => $content,
                'cover_image_path' => Art::banner("blog/{$slug}.svg", $title, $icon, $palette),
                'status' => 'published', 'published_at' => now()->subDays($daysAgo),
                'reading_minutes' => max(2, (int) ceil(str_word_count(strip_tags($content)) / 200)),
                'meta_title' => $title.' | MotoGears Blog', 'meta_description' => Str::limit(strip_tags($content), 150),
            ]);
            $post->tags()->sync(collect($tags)->map(fn ($t) => BlogTag::firstOrCreate(['slug' => Str::slug($t)], ['name' => ucwords($t)])->id));
        }
    }

    private function misc(): void
    {
        $testimonials = [
            ['Rahul Sharma', 'Bengaluru', 'Mahindra Thar', 5, 'The vehicle selector is brilliant — I found brake pads for my Thar in under a minute and they fit perfectly.'],
            ['Priya Nair', 'Kochi', 'Royal Enfield Classic 350', 5, 'Ordered a chain sprocket kit and crash guard. Genuine parts, fast delivery and great packaging.'],
            ['Arjun Reddy', 'Hyderabad', 'Toyota Fortuner', 4, 'Good prices on Monroe shocks. The GST invoice made it easy to claim for my business vehicle.'],
            ['Sneha Patil', 'Pune', 'Hyundai Creta', 5, 'Support helped me pick the right cabin filter. Loved the detailed compatibility list.'],
            ['Vikram Singh', 'Jaipur', 'KTM Duke 390', 5, 'Got the Akrapovic slip-on at a great price. Sounds fantastic!'],
            ['Ananya Iyer', 'Chennai', 'Tata Nexon', 4, 'Easy returns when I ordered the wrong wiper size. Pickup was the next day.'],
        ];
        foreach ($testimonials as $i => [$name, $loc, $vehicle, $rating, $content]) {
            Testimonial::updateOrCreate(['name' => $name], ['location' => $loc, 'vehicle' => $vehicle, 'rating' => $rating, 'content' => $content, 'is_active' => true, 'sort_order' => $i]);
        }

        foreach ([
            ['Karan Mehta', 'karan.mehta@example.com', 'Bulk order for fleet', 'We run a fleet of 12 Innova Crystas. Can you offer a corporate price on filters and brake pads?', 'new'],
            ['Divya Menon', 'divya.menon@example.com', 'Fitment question', 'Will the Classic 350 LED headlight fit the older UCE model?', 'replied'],
            ['Rohit Verma', 'rohit.verma@example.com', 'Delivery to Leh', 'Do you deliver to Leh, Ladakh? I need saddle bags before my trip next month.', 'read'],
        ] as [$name, $email, $subject, $message, $status]) {
            ContactMessage::updateOrCreate(['email' => $email, 'subject' => $subject], ['name' => $name, 'phone' => '+91 98'.random_int(10000000, 99999999), 'message' => $message, 'status' => $status]);
        }

        foreach (['rahul.sharma@example.com', 'sneha.patil@example.com', 'rider.raj@example.com', 'thar.club@example.com', 'meera.joshi@example.com'] as $email) {
            NewsletterSubscriber::updateOrCreate(['email' => $email], ['status' => 'subscribed', 'source' => 'website']);
        }

        foreach (['thar led' => 64, 'brake pads' => 58, 'motul 7100' => 51, 'classic 350' => 44, 'air filter' => 40, 'crash guard' => 33, 'dash cam' => 29, 'creta' => 27, 'chain sprocket' => 22, 'fog lamp' => 19] as $term => $hits) {
            SearchTerm::updateOrCreate(['term' => $term], ['hits' => $hits, 'results' => 5]);
        }
    }
}
