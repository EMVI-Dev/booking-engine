<?php

namespace Database\Seeders;

use App\Enums\AgentStatus;
use App\Enums\AgentUserRole;
use App\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\Package;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

class CatalogSeeder extends Seeder
{
    /**
     * Run the catalog database seeds with 10 products and 10 packages.
     */
    public function run(): void
    {
        // 1. Ensure or find demo user and agent
        $user = User::firstOrCreate(
            ['email' => 'agent@example.com'],
            [
                'name' => 'John Operator',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
            ]
        );

        /** @var Agent $agent */
        $agent = Agent::firstOrCreate(
            ['slug' => 'nusapenida-excursions'],
            [
                'name' => 'Nusa Penida Excursions & Expeditions',
                'status' => AgentStatus::Approved,
                'bio' => 'Premier local experiential operator providing certified speedboat transfers, guided marine adventures, private island charters, and premium dive gear hire across Bali & Nusa Islands.',
                'contact_whatsapp' => '+6281234567890',
                'booking_notification_email' => 'bookings@nusapenida.test',
                'billing_email' => 'finance@nusapenida.test',
                'bank_provider' => 'BCA',
                'bank_account_name' => 'PT Nusa Penida Excursions',
                'bank_account_number' => '8830192847',
                'bank_account_ref' => 'BCA - 8830192847 (PT Nusa Penida Excursions)',
                'terms_and_conditions' => "1. Free cancellation is permitted up to 24 hours prior to scheduled departure.\n2. In cases of severe marine weather or harbor master closures, full rescheduling or 100% refund is guaranteed.\n3. All passengers are covered by comprehensive maritime passenger accident insurance.\n4. Snorkeling and diving activities require participants to declare standard medical fitness.",
                'settings' => [
                    'brand_color' => '#4f46e5',
                    'whatsapp_prefilled_message' => 'Hi Nusa Penida Excursions, I would like to inquire about your packages.',
                    'social_links' => [
                        'website' => 'https://nusapenida.test',
                        'instagram' => 'https://instagram.com/nusapenidaexcursions',
                        'facebook' => 'https://facebook.com/nusapenidaexcursions',
                    ],
                    'storefront' => [
                        'allow_standalone_products' => true,
                        'show_reviews' => true,
                        'show_inclusions_preview' => true,
                        'hero_headline' => 'Unforgettable Island & Ocean Expeditions',
                        'hero_tagline' => 'Official direct bookings with certified local guides and guaranteed private charters',
                    ],
                    'payment_gateway' => [
                        'provider' => 'doku',
                        'use_custom_credentials' => false,
                        'environment' => 'sandbox',
                    ],
                ],
            ]
        );

        if (! $agent->users()->where('users.id', $user->id)->exists()) {
            $agent->users()->attach($user->id, ['role' => AgentUserRole::Owner]);
        }

        // 2. Define 10 realistic products
        $productsData = [
            [
                'name' => 'Manta Point Snorkeling Gear Set',
                'slug' => 'manta-point-snorkeling-gear-set',
                'category' => 'Equipment & Gear',
                'location' => 'Toyapakeh Harbor, Nusa Penida',
                'price' => 75000.00,
                'capacity_per_day' => 40,
                'sellable_standalone' => true,
                'description' => 'Professional anti-fog tempered glass mask, dry-top snorkel, and adjustable open-heel fins sanitized prior to each guest booking.',
                'inclusions' => ['Tempered Glass Mask', 'Dry Snorkel', 'Fins', 'Mesh Carry Bag', 'Antifog Spray'],
                'exclusions' => ['Wetsuit Rental', 'Action Camera'],
                'status' => ListingStatus::Published,
            ],
            [
                'name' => 'Fast Boat Transfer: Sanur to Nusa Penida',
                'slug' => 'fast-boat-transfer-sanur-nusa-penida',
                'category' => 'Transport & Transfers',
                'location' => 'Sanur Harbor Pier 3, Bali',
                'price' => 150000.00,
                'capacity_per_day' => 80,
                'sellable_standalone' => true,
                'description' => 'Express 35-minute speedboat crossing from Sanur Port to Nusa Penida with air-conditioned cabin and luggage assistance.',
                'inclusions' => ['One-way Speedboat Ticket', 'Port Clearance Fee', 'Passenger Insurance', 'Luggage Handling (up to 20kg)'],
                'exclusions' => ['Hotel Pick-up / Drop-off', 'Porter Tips'],
                'status' => ListingStatus::Published,
            ],
            [
                'name' => 'Private Air-Conditioned SUV & Driver (Full Day)',
                'slug' => 'private-ac-suv-driver-full-day',
                'category' => 'Transport & Transfers',
                'location' => 'Nusa Penida Island',
                'price' => 600000.00,
                'capacity_per_day' => 12,
                'sellable_standalone' => true,
                'description' => 'Comfortable 6-seater Toyota Avanza/Innova with dedicated English-speaking local driver-guide and all island fuel included for 10 hours.',
                'inclusions' => ['Private Car (10 Hours)', 'English-speaking Driver Guide', 'Fuel & Parking Fees', 'Chilled Mineral Water'],
                'exclusions' => ['Destination Entrance Tickets', 'Meals and Personal Expenses'],
                'status' => ListingStatus::Published,
            ],
            [
                'name' => 'GoPro Hero 12 Black 4K Rental with Floaty',
                'slug' => 'gopro-hero-12-underwater-rental',
                'category' => 'Equipment & Gear',
                'location' => 'Crystal Bay Dive Center',
                'price' => 200000.00,
                'capacity_per_day' => 15,
                'sellable_standalone' => true,
                'description' => 'Underwater 4K60fps action camera kit equipped with dive housing, floating hand grip, extra battery, and 64GB MicroSD card with phone transfer adapter.',
                'inclusions' => ['GoPro Hero 12 Camera', '60m Waterproof Dive Case', 'Floating Grip & Lanyard', 'High-Speed 64GB MicroSD', 'Direct iPhone/Android Card Reader'],
                'exclusions' => ['Loss or Deep Salt Damage Insurance'],
                'status' => ListingStatus::Published,
            ],
            [
                'name' => 'Certified PADI Divemaster Guiding Service',
                'slug' => 'certified-padi-divemaster-guide',
                'category' => 'Activities & Guidance',
                'location' => 'Nusa Penida Marine Protected Area',
                'price' => 450000.00,
                'capacity_per_day' => 10,
                'sellable_standalone' => true,
                'description' => 'Private 1-on-2 certified PADI divemaster accompaniment for certified divers exploring Manta Point, Crystal Bay, and SD Point drift dives.',
                'inclusions' => ['Certified PADI Divemaster Briefing', 'Dive Site Navigation', 'Emergency O2 Kit on Standby', 'Logbook Signing'],
                'exclusions' => ['Scuba Tanks and BCD Gear (Available separately)', 'Marine Park Conservation Pass'],
                'status' => ListingStatus::Published,
            ],
            [
                'name' => 'Traditional Indonesian Seafood Buffet Lunch',
                'slug' => 'traditional-seafood-buffet-lunch',
                'category' => 'Food & Dining',
                'location' => 'Ocean View Cliff Restaurant, Penida',
                'price' => 120000.00,
                'capacity_per_day' => 60,
                'sellable_standalone' => true,
                'description' => 'Fresh grilled ocean catch, satay lilit, organic farm vegetables, sambal matah, fragrant jasmine rice, and fresh tropical fruit platter.',
                'inclusions' => ['Full Buffet Access', 'Fresh Young Coconut Welcome Drink', 'Vegetarian / Halal Options', 'Panoramic Ocean Seating'],
                'exclusions' => ['Alcoholic Beverages'],
                'status' => ListingStatus::Published,
            ],
            [
                'name' => 'Kelingking & Diamond Beach Retribution Ticket Pass',
                'slug' => 'island-entrance-retribution-pass',
                'category' => 'Tickets & Passes',
                'location' => 'Nusa Penida Tourism Sites',
                'price' => 50000.00,
                'capacity_per_day' => 200,
                'sellable_standalone' => true,
                'description' => 'Official government destination pass granting fast-track entrance to Kelingking T-Rex Cliff, Diamond Beach, Broken Beach, and Angel Billabong.',
                'inclusions' => ['Official Regency Tourism Retribution', 'Sanitation Levy', 'Fast-track QR Code Voucher'],
                'exclusions' => ['Tree House Photo Spot Extra Fee'],
                'status' => ListingStatus::Published,
            ],
            [
                'name' => 'Stand-Up Paddleboard (SUP) Rental (2 Hours)',
                'slug' => 'sup-paddleboard-rental-2hr',
                'category' => 'Equipment & Gear',
                'location' => 'Crystal Bay Beach',
                'price' => 150000.00,
                'capacity_per_day' => 20,
                'sellable_standalone' => true,
                'description' => 'High-stability epoxy paddleboard with lightweight carbon paddle and safety ankle leash, ideal for calm morning bay exploration.',
                'inclusions' => ['Epoxy Paddleboard', 'Carbon Paddle', 'Safety Coiled Leash', 'Life Vest (PFD)'],
                'exclusions' => ['Instructor Lessons'],
                'status' => ListingStatus::Published,
            ],
            [
                'name' => 'Private Speedboat Charter (Half Day - 4 Hours)',
                'slug' => 'private-speedboat-charter-half-day',
                'category' => 'Transport & Transfers',
                'location' => 'Buyuk Harbor, Nusa Penida',
                'price' => 2500000.00,
                'capacity_per_day' => 4,
                'sellable_standalone' => true,
                'description' => 'Twin-engine 250HP private speedboat with licensed captain and deckhand for up to 10 guests. Custom routing to Manta Bay, Gamat Bay, and Wall Bay.',
                'inclusions' => ['Private Speedboat (4 Hours)', 'Captain & Crew', 'Marine Fuel & Harbor Clearance', 'Safety Lifejackets & First Aid', 'Ice Box with Soft Drinks'],
                'exclusions' => ['Snorkel Gear Rental', 'Drone Pilot Service'],
                'status' => ListingStatus::Published,
            ],
            [
                'name' => 'Professional Drone & Photography Package',
                'slug' => 'professional-drone-photography-package',
                'category' => 'Activities & Guidance',
                'location' => 'All Nusa Penida Highlights',
                'price' => 750000.00,
                'capacity_per_day' => 6,
                'sellable_standalone' => true,
                'description' => 'Personal creative videographer accompanying your trip with DJI 4K Drone and mirrorless camera. Delivers 25 edited high-res photos and 1 reel video within 24 hours.',
                'inclusions' => ['Dedicated Creative Photographer', 'DJI 4K Aerial Drone Shots', '25 Color-graded Photos', '60-second 4K Instagram Reel', 'Google Drive Cloud Delivery'],
                'exclusions' => ['Printed Photo Albums'],
                'status' => ListingStatus::Published,
            ],
        ];

        $createdProducts = [];
        foreach ($productsData as $p) {
            $product = Product::updateOrCreate(
                ['agent_id' => $agent->id, 'slug' => $p['slug']],
                array_merge($p, ['agent_id' => $agent->id, 'avg_rating' => '4.95'])
            );
            $createdProducts[$p['slug']] = $product;
        }

        // 3. Define 10 comprehensive Packages
        $packagesData = [
            [
                'title' => 'Ultimate Manta Point & 3-Spot Snorkeling Adventure',
                'slug' => 'ultimate-manta-point-3-spot-snorkeling',
                'category' => 'Marine Expeditions',
                'location' => 'Nusa Penida Marine Sanctuary',
                'price' => 450000.00,
                'description' => 'Experience the magic of swimming alongside majestic giant oceanic manta rays at Manta Point, followed by vibrant coral garden drifts in Gamat Bay and Crystal Bay.',
                'itinerary_text' => "07:30 - Meet at Toyapakeh Harbor & safety briefing\n08:15 - Speedboat departure to Manta Point\n09:00 - Guided swim with giant Manta Rays\n10:30 - Coral reef drift at Gamat Bay\n12:00 - Crystal Bay relaxation & lunch\n13:30 - Return to harbor with photo handover",
                'inclusions' => ['Speedboat Shared Cruise', 'Complete Snorkel & Fin Kit', 'Lifejacket', 'Certified In-water Guide', 'GoPro Undersea Photos & Videos', 'Mineral Water & Fresh Fruit'],
                'exclusions' => ['Hotel Transfers in Bali mainland', 'Wetsuit upgrades'],
                'product_slugs' => ['manta-point-snorkeling-gear-set', 'gopro-hero-12-underwater-rental'],
            ],
            [
                'title' => 'West Nusa Penida Day Tour (Kelingking, Broken Beach & Angel Billabong)',
                'slug' => 'west-nusa-penida-highlights-day-tour',
                'category' => 'Island Day Tours',
                'location' => 'West Coast, Nusa Penida',
                'price' => 750000.00,
                'description' => 'The definitive island sightseeing journey covering the world-famous Kelingking T-Rex cliff, Angel’s Billabong natural infinity pool, and Broken Beach tunnel archway in private air-conditioned comfort.',
                'itinerary_text' => "07:00 - Fast boat departure from Sanur\n08:00 - Arrival at Penida & meet private driver\n09:30 - Kelingking T-Rex viewpoint & photo stop\n12:00 - Cliffside Indonesian buffet lunch\n13:30 - Angel’s Billabong & Broken Beach\n15:30 - Crystal Bay afternoon swim\n17:00 - Return fast boat to Sanur",
                'inclusions' => ['Return Sanur Speedboat Tickets', 'Private Air-Conditioned SUV & Driver', 'All Entrance & Retribution Fees', 'Seafood Buffet Lunch', 'Chilled Bottled Water'],
                'exclusions' => ['Personal purchases', 'Treehouse photo spot tips'],
                'product_slugs' => ['fast-boat-transfer-sanur-nusa-penida', 'private-ac-suv-driver-full-day', 'island-entrance-retribution-pass', 'traditional-seafood-buffet-lunch'],
            ],
            [
                'title' => 'East Coast Wonders (Diamond Beach, Atuh & Tree House)',
                'slug' => 'east-coast-wonders-diamond-beach-atuh',
                'category' => 'Island Day Tours',
                'location' => 'East Coast, Nusa Penida',
                'price' => 800000.00,
                'description' => 'Witness iconic sunrise panoramas from the Molenteng Tree House, descend the pristine limestone steps to Diamond Beach, and unwind at tranquil Atuh Bay.',
                'itinerary_text' => "06:30 - Early fast boat from Sanur\n07:45 - Private vehicle pickup at Banjar Nyuh Harbor\n09:00 - Thousand Islands viewpoint & Rumah Pohon Treehouse\n11:00 - Diamond Beach exploration & white sand swimming\n13:00 - Hilltop lunch overlooking Atuh Bay\n15:00 - Teletubbies Green Hills panoramic drive\n16:45 - Harbor transfer and boat departure",
                'inclusions' => ['Return Fast Boat Tickets', 'Private SUV with Fuel & Driver', 'Diamond Beach & Tree House Tickets', 'Indonesian Set Lunch', 'Parking & Retribution Levies'],
                'exclusions' => ['Diamond Beach cliff swing extra fee'],
                'product_slugs' => ['fast-boat-transfer-sanur-nusa-penida', 'private-ac-suv-driver-full-day', 'island-entrance-retribution-pass', 'traditional-seafood-buffet-lunch'],
            ],
            [
                'title' => 'VIP Private Speedboat Island Charter & Snorkel Combo',
                'slug' => 'vip-private-speedboat-island-charter-combo',
                'category' => 'VIP & Private Charters',
                'location' => 'Nusa Penida & Lembongan',
                'price' => 3800000.00,
                'description' => 'The ultimate luxury maritime day for families and private groups. Cruise at your own leisure across 4 secret coves with private captain, premium snorkeling gear, and onboard GoPro photography.',
                'itinerary_text' => "08:30 - Private hotel pickup to harbor\n09:00 - Private speedboat boarding with captain\n09:45 - Private Manta Point snorkel session\n11:15 - Drift snorkel along SD Point and Mangrove Lembongan\n13:00 - Gourmet beachside seafood lunch\n14:30 - Stand-up paddleboarding & swimming at secluded bay\n16:00 - Sunset return cruise",
                'inclusions' => ['Private 4-Hour Speedboat Charter', 'Snorkel Gear for up to 6 guests', 'GoPro Camera with MicroSD handover', 'Seafood Lunch for group', 'Private Guide & Deck Crew', 'Cooler with Soft Drinks & Beer'],
                'exclusions' => ['Scuba Dive Gear (optional add-on)'],
                'product_slugs' => ['private-speedboat-charter-half-day', 'manta-point-snorkeling-gear-set', 'gopro-hero-12-underwater-rental', 'traditional-seafood-buffet-lunch'],
            ],
            [
                'title' => '2-Tank Certified Scuba Diving Expedition at Manta Point & SD Point',
                'slug' => '2-tank-certified-scuba-diving-expedition',
                'category' => 'Marine Expeditions',
                'location' => 'Nusa Penida Marine Sanctuary',
                'price' => 1250000.00,
                'description' => 'World-class deep marine scuba dive for certified Open Water and Advanced divers. Encounter oceanic sunfish (Mola-Mola during season) and resident reef mantas.',
                'itinerary_text' => "07:45 - Dive center check-in & equipment sizing\n08:30 - Boat departure to Dive 1: Manta Point (Max 18m)\n11:00 - Surface interval with hot coffee, tea & snacks\n12:00 - Dive 2: North Coast Drift at SD Point (Max 22m)\n13:30 - Return to dive base & lunch\n14:30 - Logbook logging with PADI Divemaster",
                'inclusions' => ['2 Guided Boat Dives', 'PADI Divemaster Guidance (1:3 ratio)', 'Tanks & Weights', 'Surface Interval Snacks & Hot Drinks', 'Full Indonesian Buffet Lunch', 'Marine Park Pass'],
                'exclusions' => ['Full Set Scuba Gear Rental (BCD, Reg, Wetsuit)', 'Dive Computer'],
                'product_slugs' => ['certified-padi-divemaster-guide', 'traditional-seafood-buffet-lunch'],
            ],
            [
                'title' => 'Complete Nusa Penida in 1 Day: Best of East + West Tour',
                'slug' => 'complete-nusa-penida-east-west-in-one-day',
                'category' => 'Island Day Tours',
                'location' => 'Full Island Highlights',
                'price' => 950000.00,
                'description' => 'Designed for travelers with limited time who want to capture both Kelingking Cliff on the West and Diamond Beach on the East in an optimized private route.',
                'itinerary_text' => "06:30 - First boat departure from Sanur\n07:45 - Start with East Coast: Diamond Beach & Treehouse\n11:30 - Scenic lunch break\n13:00 - West Coast: Kelingking T-Rex Cliff\n15:00 - Broken Beach & Angel Billabong\n16:30 - Port drop-off for final boat back to Bali",
                'inclusions' => ['Return Speedboat Tickets', 'Private Driver & All-Island Vehicle', 'All Ticket Retributions', 'Lunch & Bottled Water', 'Port Tax'],
                'exclusions' => ['Personal snacks'],
                'product_slugs' => ['fast-boat-transfer-sanur-nusa-penida', 'private-ac-suv-driver-full-day', 'island-entrance-retribution-pass', 'traditional-seafood-buffet-lunch'],
            ],
            [
                'title' => 'Instagram Photographer & Drone Explorer Tour',
                'slug' => 'instagram-photographer-drone-explorer-tour',
                'category' => 'Island Day Tours',
                'location' => 'Nusa Penida Scenic Spots',
                'price' => 1450000.00,
                'description' => 'Travel like an influencer! Dedicated professional creator accompanies your private tour with a DJI 4K Drone and high-end mirrorless camera to ensure jaw-dropping content.',
                'itinerary_text' => "07:30 - Private pickup at harbor\n08:30 - Diamond Beach staircase aerial drone shoots\n11:00 - Treehouse & Thousand Island portraits\n13:00 - Cliffside dining with panoramic views\n14:30 - Kelingking T-Rex cliff epic wide-angle capture\n16:30 - Instant same-day photo preview handover",
                'inclusions' => ['Private Car & Driver', 'Dedicated Pro Photographer & Drone Pilot', '25 Retouched Photos + 1 Edited 4K Reel', 'All Destination Entry Tickets', 'Lunch & Drinks'],
                'exclusions' => ['Costume / Dress Changes (BYO)'],
                'product_slugs' => ['professional-drone-photography-package', 'private-ac-suv-driver-full-day', 'island-entrance-retribution-pass', 'traditional-seafood-buffet-lunch'],
            ],
            [
                'title' => 'Crystal Bay Sunset Cruise & Stand-Up Paddleboard Session',
                'slug' => 'crystal-bay-sunset-cruise-paddleboard-session',
                'category' => 'Marine Expeditions',
                'location' => 'Crystal Bay, West Penida',
                'price' => 380000.00,
                'description' => 'Unwind in the golden hour with an easy stand-up paddleboarding session across Crystal Bay’s sheltered lagoon, topped with fresh coconuts and sunset views.',
                'itinerary_text' => "15:00 - Meet at Crystal Bay Dive Shack\n15:30 - Paddleboard setup & safety overview\n16:00 - Guided calm water paddle along the cove\n17:30 - Fresh young coconut and beach beanbag sunset relaxation",
                'inclusions' => ['2-Hour SUP Board & Paddle Rental', 'Safety Leash & Life Vest', 'Fresh Young Coconut', 'Beach Chair Access'],
                'exclusions' => ['Vehicle transport to Crystal Bay'],
                'product_slugs' => ['sup-paddleboard-rental-2hr'],
            ],
            [
                'title' => '2-Day 1-Night All-Inclusive Nusa Penida Island Escape',
                'slug' => '2-day-1-night-all-inclusive-island-escape',
                'category' => 'Multi-Day Packages',
                'location' => 'Nusa Penida Island',
                'price' => 1950000.00,
                'description' => 'Slow down and experience Nusa Penida without the day-tripper crowds. Includes boutique resort stay, East & West island tours, and morning manta ray snorkel.',
                'itinerary_text' => "Day 1: Speedboat from Sanur, West Coast Highlights, Sunset at Crystal Bay, Resort Check-in.\nDay 2: Morning Manta Snorkel boat, East Coast Diamond Beach exploration, return boat to Sanur.",
                'inclusions' => ['1 Night Boutique Hotel Stay with Breakfast', 'Return Speedboat Tickets', '2 Days Private Car & Driver', '3-Spot Manta Snorkel Tour with Gear', '2 Lunches & All Entry Passes'],
                'exclusions' => ['Day 1 Dinner (freedom of choice in town)'],
                'product_slugs' => ['fast-boat-transfer-sanur-nusa-penida', 'private-ac-suv-driver-full-day', 'manta-point-snorkeling-gear-set', 'island-entrance-retribution-pass', 'traditional-seafood-buffet-lunch'],
            ],
            [
                'title' => 'Private Snorkel & Island Crossing Combo Package',
                'slug' => 'private-snorkel-island-crossing-combo',
                'category' => 'Marine Expeditions',
                'location' => 'Nusa Penida & Ceningan Channel',
                'price' => 650000.00,
                'description' => 'Seamless combination of morning speedboat transfers, private snorkeling equipment, and half-day guided vehicle island exploration.',
                'itinerary_text' => "08:00 - Fast boat crossing to Nusa Penida\n09:00 - 2-hour guided snorkeling at Toyapakeh Wall & Gamat\n11:30 - Lunch overlooking ocean\n13:00 - Kelingking Cliff viewpoint visit\n16:00 - Fast boat return to Bali mainland",
                'inclusions' => ['Return Boat Tickets', 'Snorkel Gear & Lifevest', 'Shared In-Water Guide', 'Private SUV for Island Afternoon', 'Entry Tickets & Lunch'],
                'exclusions' => ['GoPro Rental (optional add-on)'],
                'product_slugs' => ['fast-boat-transfer-sanur-nusa-penida', 'manta-point-snorkeling-gear-set', 'private-ac-suv-driver-full-day', 'traditional-seafood-buffet-lunch'],
            ],
        ];

        foreach ($packagesData as $pkg) {
            $productSlugs = $pkg['product_slugs'];
            unset($pkg['product_slugs']);

            $package = Package::updateOrCreate(
                ['agent_id' => $agent->id, 'slug' => $pkg['slug']],
                array_merge($pkg, ['agent_id' => $agent->id, 'status' => ListingStatus::Published, 'avg_rating' => '4.98'])
            );

            // Link products with pivot
            $syncData = [];
            foreach ($productSlugs as $pSlug) {
                $syncData[$createdProducts[$pSlug]->id] = ['quantity_required' => 1];
            }
            $package->products()->sync($syncData);
        }
    }
}
