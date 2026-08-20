<?php

namespace Database\Seeders;

use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Models\Agent;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Review;
use Illuminate\Database\Seeder;

class SampleAgentCatalogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(?string $agentId = '01m0f78y60jt44qzeyvgp3nmp7'): void
    {
        $agent = Agent::find($agentId) ?? Agent::first();

        if (! $agent) {
            return;
        }

        // 1. Update Agent Profile details
        $agent->update([
            'name' => 'Bali Ride Tours',
            'bio' => 'Premier marine expeditions, daily manta ray snorkeling safaris, sunset cruises, and fast boat charters across Nusa Penida, Lembongan, and the Gili Islands.',
            'contact_whatsapp' => '087761317159',
            'bank_provider' => 'BCA',
            'bank_account_name' => 'PT Bali Ride Tours',
            'bank_account_number' => '5670660961',
            'terms_and_conditions' => "1. Free cancellation is available up to the stated cutoff window.\n2. In case of extreme weather or port authority closure, guests may reschedule or receive a full refund.\n3. Please arrive at Sanur Harbour Terminal at least 30 minutes prior to departure.\n4. Snorkeling equipment and life jackets are provided for all guests.",
        ]);

        // 2. Create Reusable Inventory Products
        $prod1 = Product::updateOrCreate(
            ['agent_id' => $agent->id, 'name' => 'Speedboat Seat (Sanur ⇄ Nusa Penida Express)'],
            [
                'slug' => 'speedboat-seat-express',
                'category' => 'Transport',
                'location' => 'Sanur Harbour, Bali',
                'capacity_per_day' => 45,
                'sellable_standalone' => true,
                'price' => 175000.00,
                'description' => 'Fast twin-engine passenger boat transfer between Sanur Harbour and Banjar Nyuh Port (approx 35 minutes).',
                'inclusions' => ['One-way fastboat ticket', 'Port service tax', 'Insurance coverage', 'Luggage handling up to 20kg'],
                'exclusions' => ['Hotel pickup/drop-off', 'Nusa Penida regional entrance fee'],
                'status' => ListingStatus::Published,
            ]
        );

        $prod2 = Product::updateOrCreate(
            ['agent_id' => $agent->id, 'name' => 'Pro Snorkeling Gear & Lifevest Set'],
            [
                'slug' => 'pro-snorkeling-gear-set',
                'category' => 'Rental',
                'location' => 'Toyapakeh, Nusa Penida',
                'capacity_per_day' => 30,
                'sellable_standalone' => true,
                'price' => 75000.00,
                'description' => 'High-grade anti-fog tempered glass mask, dry-top silicone snorkel, adjustable open-heel fins, and high-buoyancy lifevest.',
                'inclusions' => ['Sanitized mask & snorkel', 'Fins (Sizes 36-46 available)', 'Coast guard certified life jacket'],
                'exclusions' => ['Loss or damage deposit'],
                'status' => ListingStatus::Published,
            ]
        );

        $prod3 = Product::updateOrCreate(
            ['agent_id' => $agent->id, 'name' => 'Underwater 4K Action Camera Rental'],
            [
                'slug' => 'underwater-action-camera',
                'category' => 'Equipment',
                'location' => 'Toyapakeh, Nusa Penida',
                'capacity_per_day' => 12,
                'sellable_standalone' => true,
                'price' => 150000.00,
                'description' => 'GoPro Hero 11 with waterproof dive housing (up to 40m), floating grip handle, and high-speed MicroSD card.',
                'inclusions' => ['GoPro Hero 11 camera', 'Waterproof dive housing', 'Floating grip + lanyard', 'MicroSD 64GB card (kept by guest)'],
                'exclusions' => ['Extra spare batteries'],
                'status' => ListingStatus::Published,
            ]
        );

        $prod4 = Product::updateOrCreate(
            ['agent_id' => $agent->id, 'name' => 'Certified Local Marine Guide & Spotter'],
            [
                'slug' => 'certified-marine-guide',
                'category' => 'Service',
                'location' => 'Manta Bay & Crystal Bay',
                'capacity_per_day' => 10,
                'sellable_standalone' => true,
                'price' => 250000.00,
                'description' => 'Experienced PADI certified local divemaster and marine spotter specializing in Manta Ray encounters and ocean safety.',
                'inclusions' => ['Certified local guide', 'Ocean safety supervision', 'Underwater photography assistance'],
                'exclusions' => ['Guide gratuity/tip'],
                'status' => ListingStatus::Published,
            ]
        );

        $prod5 = Product::updateOrCreate(
            ['agent_id' => $agent->id, 'name' => 'Private Speedboat Charter (Full Day)'],
            [
                'slug' => 'private-speedboat-charter',
                'category' => 'Charter',
                'location' => 'Nusa Penida & Lembongan',
                'capacity_per_day' => 3,
                'sellable_standalone' => true,
                'price' => 4500000.00,
                'description' => 'Exclusive 32ft private speedboat with twin 250HP Suzuki outboards, Bluetooth sound system, shaded seating, and fresh water shower.',
                'inclusions' => ['Private boat + skipper & crew', 'Fuel & port clearances', 'Cooler box with ice', 'Fresh towel service'],
                'exclusions' => ['Catering meals (available on request)', 'Alcoholic drinks'],
                'status' => ListingStatus::Published,
            ]
        );

        $prod6 = Product::updateOrCreate(
            ['agent_id' => $agent->id, 'name' => 'Island Private SUV & Driver (Full Day)'],
            [
                'slug' => 'private-island-suv-driver',
                'category' => 'Transport',
                'location' => 'Nusa Penida Island',
                'capacity_per_day' => 15,
                'sellable_standalone' => true,
                'price' => 650000.00,
                'description' => 'Air-conditioned 6-seater private SUV with friendly English-speaking local driver-guide for 10 hours of customized sightseeing.',
                'inclusions' => ['Private vehicle for 10 hours', 'English-speaking driver guide', 'All island fuel & parking fees', 'Bottled mineral water'],
                'exclusions' => ['Destination entry tickets', 'Lunch expenses'],
                'status' => ListingStatus::Published,
            ]
        );

        $prod7 = Product::updateOrCreate(
            ['agent_id' => $agent->id, 'name' => 'Stand-Up Paddleboard & Clear Kayak Rental'],
            [
                'slug' => 'paddleboard-clear-kayak-rental',
                'category' => 'Rental',
                'location' => 'Crystal Bay, Nusa Penida',
                'capacity_per_day' => 20,
                'sellable_standalone' => true,
                'price' => 125000.00,
                'description' => 'Premium transparent ocean kayak and high-stability stand-up paddleboard with safety leash and lifevest for 2 hours.',
                'inclusions' => ['Transparent kayak or SUP board', 'Carbon paddle', 'Coiled ankle leash', 'US Coast Guard certified life jacket'],
                'exclusions' => ['Instructional coaching lesson'],
                'status' => ListingStatus::Published,
            ]
        );

        // 3. Create 7 Packages with product composition
        $pkg1 = Package::updateOrCreate(
            ['agent_id' => $agent->id, 'slug' => 'nusa-penida-ultimate-3-point-snorkeling-safari'],
            [
                'title' => 'Nusa Penida Ultimate 3-Point Snorkel Safari',
                'category' => 'Day Experience',
                'location' => 'Manta Bay, Crystal Bay & Gamat Bay',
                'price' => 650000.00,
                'description' => 'Our flagship marine adventure! Swim alongside majestic giant Manta Rays at Manta Bay, explore vibrant coral gardens at Crystal Bay, and drift over crystal-clear waters at Gamat Bay.',
                'itinerary_text' => "07:30 — Check-in at Sanur Harbour & boarding\n08:00 — Fastboat cruise to Nusa Penida\n09:00 — Manta Bay snorkeling expedition with marine guide\n11:00 — Crystal Bay coral reef discovery\n12:30 — Oceanfront Indonesian buffet lunch\n14:00 — Gamat Bay drift snorkel\n16:00 — Return fastboat transfer to Sanur",
                'inclusions' => [
                    'Return fastboat transfers from Sanur Bali',
                    'Snorkeling stops at Manta Bay, Crystal Bay & Gamat Bay',
                    'All sanitized snorkeling gear & life vests',
                    'Indonesian buffet lunch & bottled mineral water',
                    'Certified marine guide & ocean spotter',
                    'Free underwater GoPro photos & videos',
                ],
                'exclusions' => [
                    'Hotel pickup/drop-off transfer in Bali (optional add-on)',
                    'Nusa Penida island environmental tax (Rp 25.000/pax)',
                ],
                'free_cancellation_hours' => 24,
                'advance_booking_hours' => 12,
                'status' => ListingStatus::Published,
            ]
        );

        $pkg1->products()->sync([
            $prod1->id => ['quantity_required' => 2],
            $prod2->id => ['quantity_required' => 1],
            $prod4->id => ['quantity_required' => 1],
        ]);

        $pkg2 = Package::updateOrCreate(
            ['agent_id' => $agent->id, 'slug' => 'private-sunset-yacht-snorkeling-cruise'],
            [
                'title' => 'Private Sunset Fastboat & Snorkel Cruise',
                'category' => 'Private Tour',
                'location' => 'Nusa Ceningan & Penida Coast',
                'price' => 3500000.00,
                'description' => 'An unforgettable VIP private voyage for families and private groups. Cruise along towering limestone cliffs, snorkel with sea turtles, and watch the Bali sunset with tropical refreshments.',
                'itinerary_text' => "13:30 — Private boat boarding at Sanur Harbour\n14:30 — Snorkeling at Wall Bay & Secret Turtle Point\n16:30 — Cruise along Ceningan Yellow Bridge & Cliff Views\n17:45 — Golden Hour sunset view with chilled drinks & tropical fruit platter\n19:00 — Return cruise to Sanur Harbour",
                'inclusions' => [
                    'Private luxury speedboat with captain & crew for up to 8 pax',
                    'Full snorkeling gear for all guests',
                    'Chilled tropical fruit platter & coconut water',
                    'Bluetooth premium sound system onboard',
                    'GoPro underwater action camera rental included',
                ],
                'exclusions' => [
                    'Personal alcoholic beverages (BYO allowed at no corkage fee)',
                ],
                'free_cancellation_hours' => 48,
                'advance_booking_hours' => 24,
                'status' => ListingStatus::Published,
            ]
        );

        $pkg2->products()->sync([
            $prod5->id => ['quantity_required' => 1],
            $prod2->id => ['quantity_required' => 4],
            $prod3->id => ['quantity_required' => 1],
            $prod4->id => ['quantity_required' => 1],
        ]);

        $pkg3 = Package::updateOrCreate(
            ['agent_id' => $agent->id, 'slug' => 'west-coast-highlights-trex-cliff-adventure'],
            [
                'title' => 'West Coast Highlights & T-Rex Cliff Adventure',
                'category' => 'Island Tour',
                'location' => 'Kelingking, Broken Beach & Angel Billabong',
                'price' => 750000.00,
                'description' => 'Explore the iconic geological marvels of Western Nusa Penida. Marvel at the dramatic T-Rex shaped Kelingking Cliff, natural infinity pool Angel’s Billabong, and volcanic tunnel Broken Beach.',
                'itinerary_text' => "07:00 — Fastboat departure from Sanur Harbour\n08:00 — Arrive at Penida & meet private driver\n09:30 — Kelingking Secret Point & T-Rex Cliff photography\n12:00 — Cliffside Indonesian seafood lunch\n13:30 — Angel’s Billabong & Broken Beach exploration\n15:30 — Crystal Bay afternoon swim & relaxation\n17:00 — Return fastboat to Sanur",
                'inclusions' => [
                    'Return fastboat tickets Sanur ⇄ Nusa Penida',
                    'Private air-conditioned SUV with English-speaking driver',
                    'All island retribution tickets & parking fees',
                    'Seafood buffet lunch & mineral water',
                ],
                'exclusions' => [
                    'Personal purchases & souvenirs',
                ],
                'free_cancellation_hours' => 24,
                'advance_booking_hours' => 12,
                'status' => ListingStatus::Published,
            ]
        );

        $pkg3->products()->sync([
            $prod1->id => ['quantity_required' => 2],
            $prod6->id => ['quantity_required' => 1],
        ]);

        $pkg4 = Package::updateOrCreate(
            ['agent_id' => $agent->id, 'slug' => 'east-nusa-penida-diamond-beach-escape'],
            [
                'title' => 'East Nusa Penida Diamond Beach & Treehouse Escape',
                'category' => 'Island Tour',
                'location' => 'Diamond Beach, Atuh & Tree House',
                'price' => 800000.00,
                'description' => 'Discover the pristine white sands of Diamond Beach, panoramic Thousand Islands viewpoints from Molenteng Treehouse, and tranquil turquoise waters of Atuh Beach.',
                'itinerary_text' => "06:30 — Early morning fastboat crossing from Sanur\n07:45 — Meet private driver at Banjar Nyuh Port\n09:00 — Thousand Island viewpoint & Rumah Pohon Tree House\n11:00 — Diamond Beach staircase descent and swimming\n13:00 — Scenic hilltop lunch overlooking Atuh Bay\n15:00 — Teletubbies Green Hills scenic drive\n16:45 — Harbor transfer for return fastboat",
                'inclusions' => [
                    'Return fastboat tickets',
                    'Private SUV with dedicated local driver',
                    'Entry passes for Diamond Beach & Molenteng Tree House',
                    'Indonesian set lunch with fresh coconut',
                ],
                'exclusions' => [
                    'Diamond Beach cliff swing optional fee',
                ],
                'free_cancellation_hours' => 24,
                'advance_booking_hours' => 12,
                'status' => ListingStatus::Published,
            ]
        );

        $pkg4->products()->sync([
            $prod1->id => ['quantity_required' => 2],
            $prod6->id => ['quantity_required' => 1],
        ]);

        $pkg5 = Package::updateOrCreate(
            ['agent_id' => $agent->id, 'slug' => 'manta-point-scuba-diving-marine-expedition'],
            [
                'title' => 'Manta Point Scuba Diving & Marine Expedition',
                'category' => 'Scuba Diving',
                'location' => 'Manta Point & SD Point, Penida',
                'price' => 1250000.00,
                'description' => '2-tank guided boat dive for certified divers. Witness resident reef manta cleaning stations and thrilling drift dives along the north coral wall.',
                'itinerary_text' => "07:45 — Dive center meet-up & equipment fitting\n08:30 — Boat departure to Manta Point (Dive 1)\n11:00 — Surface interval with hot coffee & snacks\n12:00 — Drift dive at SD Point (Dive 2)\n13:30 — Return to base & buffet lunch\n14:30 — Logbook verification with Divemaster",
                'inclusions' => [
                    '2 guided boat dives with certified PADI Divemaster',
                    'Scuba tanks, weights & dive boat transfer',
                    'Buffet lunch, tropical fruits & hot drinks',
                    'Marine Protected Area retribution pass',
                ],
                'exclusions' => [
                    'Full dive gear rental (BCD/Regulator)',
                ],
                'free_cancellation_hours' => 48,
                'advance_booking_hours' => 24,
                'status' => ListingStatus::Published,
            ]
        );

        $pkg5->products()->sync([
            $prod4->id => ['quantity_required' => 1],
            $prod2->id => ['quantity_required' => 1],
        ]);

        $pkg6 = Package::updateOrCreate(
            ['agent_id' => $agent->id, 'slug' => 'complete-island-east-west-discovery-tour'],
            [
                'title' => 'Complete Island East + West Discovery Tour',
                'category' => 'Island Tour',
                'location' => 'Full Nusa Penida Highlights',
                'price' => 950000.00,
                'description' => 'The ultimate comprehensive 1-day itinerary combining Diamond Beach on the East and Kelingking T-Rex Cliff on the West in one seamless private journey.',
                'itinerary_text' => "06:30 — Early boat departure from Sanur\n07:45 — Start with East Coast: Diamond Beach & Treehouse\n11:30 — Scenic hilltop lunch\n13:00 — West Coast: Kelingking T-Rex Cliff\n15:00 — Broken Beach & Angel Billabong\n16:30 — Return fastboat to Bali mainland",
                'inclusions' => [
                    'Return fastboat tickets',
                    'Private all-day vehicle with driver & fuel',
                    'All destination entry passes & retribution levies',
                    'Indonesian buffet lunch & bottled mineral water',
                ],
                'exclusions' => [
                    'Personal snacks & optional photo spot tips',
                ],
                'free_cancellation_hours' => 24,
                'advance_booking_hours' => 12,
                'status' => ListingStatus::Published,
            ]
        );

        $pkg6->products()->sync([
            $prod1->id => ['quantity_required' => 2],
            $prod6->id => ['quantity_required' => 1],
        ]);

        $pkg7 = Package::updateOrCreate(
            ['agent_id' => $agent->id, 'slug' => 'vip-luxury-private-island-speedboat-experience'],
            [
                'title' => 'VIP Luxury Private Island Speedboat Experience',
                'category' => 'Private Tour',
                'location' => 'Nusa Penida & Lembongan Coves',
                'price' => 4800000.00,
                'description' => 'Exclusive private charter across 4 secluded island bays. Includes private speedboat, full snorkel set, GoPro 4K recording, stand-up paddleboarding, and premium crew service.',
                'itinerary_text' => "08:30 — Private harbour reception & VIP boat boarding\n09:15 — Private Manta Bay snorkel session\n11:00 — Gamat Bay drift snorkel & clear kayak session\n12:30 — Gourmet beachside lunch\n14:00 — Crystal Bay stand-up paddleboarding\n16:00 — Sunset cruise return to Bali",
                'inclusions' => [
                    'Full-day private speedboat with captain & crew',
                    'Snorkeling gear & lifejackets for up to 8 guests',
                    'GoPro Hero 11 underwater recording with MicroSD handover',
                    'Stand-up paddleboard & clear kayak usage',
                    'Gourmet lunch & ice-cold refreshments',
                ],
                'exclusions' => [
                    'Premium imported wine & spirits (BYO welcome)',
                ],
                'free_cancellation_hours' => 48,
                'advance_booking_hours' => 24,
                'status' => ListingStatus::Published,
            ]
        );

        $pkg7->products()->sync([
            $prod5->id => ['quantity_required' => 1],
            $prod2->id => ['quantity_required' => 6],
            $prod3->id => ['quantity_required' => 1],
            $prod4->id => ['quantity_required' => 1],
            $prod7->id => ['quantity_required' => 2],
        ]);

        // 4. Create Sample Verified Reservations & Payments
        $res1 = Reservation::updateOrCreate(
            ['agent_id' => $agent->id, 'guest_email' => 'sarah.miller@example.com'],
            [
                'bookable_type' => 'package',
                'bookable_id' => $pkg1->id,
                'guest_name' => 'Sarah Miller',
                'guest_contact' => '081234567890',
                'requested_date' => now()->addDays(3)->toDateString(),
                'pax_count' => 2,
                'notes' => 'Vegetarian meal for 1 pax please.',
                'terms_snapshot' => $pkg1->generateTermsSnapshot(),
                'status' => ReservationStatus::Confirmed,
                'hold_expires_at' => null,
            ]
        );

        Payment::updateOrCreate(
            ['reservation_id' => $res1->id],
            [
                'amount' => 1300000.00,
                'gateway' => 'doku',
                'gateway_ref' => 'INV-SARAH-'.time(),
                'split_details' => [
                    'commission_rate' => 0.10,
                    'platform_commission' => 130000.00,
                    'agent_amount' => 1170000.00,
                ],
                'status' => PaymentStatus::Paid,
            ]
        );

        $res2 = Reservation::updateOrCreate(
            ['agent_id' => $agent->id, 'guest_email' => 'david.clark@example.com'],
            [
                'bookable_type' => 'package',
                'bookable_id' => $pkg2->id,
                'guest_name' => 'David Clark',
                'guest_contact' => '082199887766',
                'requested_date' => now()->addDays(6)->toDateString(),
                'pax_count' => 6,
                'notes' => 'Celebrating wedding anniversary.',
                'terms_snapshot' => $pkg2->generateTermsSnapshot(),
                'status' => ReservationStatus::Confirmed,
                'hold_expires_at' => null,
            ]
        );

        Payment::updateOrCreate(
            ['reservation_id' => $res2->id],
            [
                'amount' => 5800000.00,
                'gateway' => 'doku',
                'gateway_ref' => 'INV-DAVID-'.time(),
                'split_details' => [
                    'commission_rate' => 0.10,
                    'platform_commission' => 580000.00,
                    'agent_amount' => 5220000.00,
                ],
                'status' => PaymentStatus::Paid,
            ]
        );

        // 5. Create Verified Guest Reviews
        Review::updateOrCreate(
            ['agent_id' => $agent->id, 'reservation_id' => $res1->id],
            [
                'bookable_type' => 'package',
                'bookable_id' => $pkg1->id,
                'rating' => 5,
                'comment' => 'Swimming with 4 giant Manta Rays was the highlight of our trip to Bali! The crew was super attentive, equipment was spotless, and the free GoPro footage was sent to us on the same evening.',
            ]
        );

        Review::updateOrCreate(
            ['agent_id' => $agent->id, 'reservation_id' => $res2->id],
            [
                'bookable_type' => 'package',
                'bookable_id' => $pkg2->id,
                'rating' => 5,
                'comment' => 'Booked the private sunset cruise for our anniversary. The boat was immaculate, captain knew all the quiet spots, and the sunset was breathtaking.',
            ]
        );
    }
}
