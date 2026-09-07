<?php

namespace Database\Seeders;

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Enums\PaymentStatus;
use App\Enums\ReservationStatus;
use App\Enums\WalletTransactionStatus;
use App\Enums\WalletTransactionType;
use App\Models\Guest;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DomainResolverService;
use App\Services\MediaStore;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DemoOperatorSeeder extends Seeder
{
    /**
     * Seed the public demo operator. Safe to re-run: only this operator is wiped.
     */
    public function run(): void
    {
        Plan::seedDefaultPlans();

        $this->forgetRetiredSampleOperators();
        $this->wipeDemoOperators();
        $this->seedDemoOperator();
    }

    /**
     * Remove the old Bali Ride Tours sample so it cannot sit next to demo.
     */
    protected function forgetRetiredSampleOperators(): void
    {
        Operator::query()
            ->whereIn('slug', ['bali-ride-tours'])
            ->orWhere('booking_notification_email', 'bookings@baliridetours.com')
            ->delete();

        User::query()->where('email', 'baliridetours@gmail.com')->delete();
    }

    protected function wipeDemoOperators(): void
    {
        $media = app(MediaStore::class);

        $ids = Operator::query()
            ->where(function ($query): void {
                $query->where('is_demo', true)
                    ->orWhere('slug', config('demo.slug', 'demo'));
            })
            ->pluck('id');

        foreach ($ids as $id) {
            $media->deleteDirectory($media->directoryFor($id));
        }

        $media->deleteDirectory('demo');

        Operator::query()->whereIn('id', $ids)->delete();
    }

    protected function seedDemoOperator(): void
    {
        $email = (string) config('demo.email', 'demo@travelengine.online');
        $password = config('demo.password');
        $name = (string) config('demo.name', 'Demo Tours');
        $slug = (string) config('demo.slug', 'demo');

        if (! is_string($password) || $password === '') {
            if (app()->isProduction()) {
                throw new RuntimeException('Set DEMO_OPERATOR_PASSWORD in .env before seeding the demo operator.');
            }

            $password = 'password';
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'email_verified_at' => now(),
                'is_admin' => false,
            ]
        );

        $agency = Plan::query()->where('slug', 'agency')->first() ?? Plan::getDefaultPlan();
        $platformDomain = app(DomainResolverService::class)->getPlatformDomain();

        /** @var Operator $operator */
        $operator = Operator::query()->create([
            'name' => $name,
            'slug' => $slug,
            'is_demo' => true,
            'status' => OperatorStatus::Approved,
            'plan_id' => $agency->id,
            'subscribed_at' => now(),
            'plan_expires_at' => null,
            'subscription_auto_renew' => false,
            'bio' => 'A sample tour operator so you can click around TravelEngine. Checkout is off. This catalog resets every day.',
            'contact_whatsapp' => '081234567890',
            'booking_notification_email' => $email,
            'billing_email' => $email,
            'bank_provider' => 'BCA',
            'bank_account_name' => 'Demo Tours',
            'bank_account_number' => '0000000000',
            'bank_account_ref' => 'BCA - 0000000000 (Demo Tours)',
            'logo_path' => null,
            'banner_path' => null,
            'terms_and_conditions' => "1. This is a demo storefront. Guests cannot pay.\n2. The catalog, bookings, and reviews reset every day.\n3. Nothing here moves real money.",
            'settings' => [
                'brand_color' => '#0f766e',
                'whatsapp_prefilled_message' => 'Hi Demo Tours, I am looking around the TravelEngine demo.',
                'whatsapp_schedule' => [
                    'mode' => 'schedule',
                    'timezone' => 'Asia/Makassar',
                    'start_time' => '08:00',
                    'end_time' => '18:00',
                    'days' => ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'],
                ],
                'social_links' => [
                    'website' => 'https://travelengine.online',
                ],
                'storefront' => [
                    'allow_standalone_products' => true,
                    'show_reviews' => true,
                    'show_inclusions_preview' => true,
                    'booking_confirmation_mode' => 'automatic',
                    'hero_headline' => 'See how a tour operator looks on TravelEngine',
                    'hero_tagline' => 'Browse trips and the operator desk. Checkout stays off so nothing is charged.',
                    'hero_image_url' => null,
                ],
            ],
        ]);

        $logoPath = $this->storeDemoImage($operator, $this->unsplash('1544644181-1484b3fdfc62', 400), MediaStore::LOGO_MAX_WIDTH, 'brand');
        $bannerPath = $this->storeDemoImage($operator, $this->unsplash('1507525428034-b723cf961d3e', 1600), MediaStore::COVER_MAX_WIDTH, 'brand');
        $settings = $operator->settings ?? [];
        $settings['storefront']['hero_image_url'] = app(MediaStore::class)->url($bannerPath);

        $operator->update([
            'logo_path' => $logoPath,
            'banner_path' => $bannerPath,
            'settings' => $settings,
        ]);

        $operator->users()->syncWithoutDetaching([
            $user->id => ['role' => OperatorUserRole::Owner->value],
        ]);

        OperatorDomain::query()->create([
            'operator_id' => $operator->id,
            'type' => DomainType::Subdomain,
            'domain' => $slug.'.'.$platformDomain,
            'is_primary' => true,
            'status' => DomainStatus::Active,
            'verified_at' => now(),
        ]);

        if ($platformDomain !== 'travelengine.online') {
            OperatorDomain::query()->firstOrCreate(
                ['domain' => $slug.'.travelengine.online'],
                [
                    'operator_id' => $operator->id,
                    'type' => DomainType::Subdomain,
                    'is_primary' => false,
                    'status' => DomainStatus::Active,
                    'verified_at' => now(),
                ]
            );
        }

        $products = $this->seedActivities($operator);
        $packages = $this->seedPackages($operator, $products);
        $this->seedSampleBookings($operator, $packages);
    }

    /**
     * @return array<string, Product>
     */
    protected function seedActivities(Operator $operator): array
    {
        $products = [];

        foreach ($this->activityDefinitions() as $definition) {
            $products[$definition['slug']] = Product::query()->create([
                'operator_id' => $operator->id,
                'name' => $definition['name'],
                'slug' => $definition['slug'],
                'category' => $definition['category'],
                'location' => $definition['location'],
                'capacity_per_day' => $definition['capacity'],
                'sellable_standalone' => true,
                'price' => $definition['price'],
                'cover_photo' => $this->storeDemoImage(
                    $operator,
                    $this->unsplash($definition['photo'], 800),
                    MediaStore::COVER_MAX_WIDTH,
                    'products/covers',
                ),
                'gallery' => array_map(
                    fn (string $photoId): string => $this->storeDemoImage(
                        $operator,
                        $this->unsplash($photoId, 800),
                        MediaStore::COVER_MAX_WIDTH,
                        'products/gallery',
                    ),
                    $definition['gallery'],
                ),
                'description' => $definition['description'],
                'inclusions' => $definition['inclusions'],
                'exclusions' => $definition['exclusions'],
                'status' => ListingStatus::Published,
            ]);
        }

        return $products;
    }

    /**
     * @param  array<string, Product>  $products
     * @return array<string, Package>
     */
    protected function seedPackages(Operator $operator, array $products): array
    {
        $packages = [];

        foreach ($this->packageDefinitions() as $definition) {
            $package = Package::query()->create([
                'operator_id' => $operator->id,
                'slug' => $definition['slug'],
                'title' => $definition['title'],
                'category' => $definition['category'],
                'location' => $definition['location'],
                'price' => $definition['price'],
                'cover_photo' => $this->storeDemoImage(
                    $operator,
                    $this->unsplash($definition['photo'], 1200),
                    MediaStore::COVER_MAX_WIDTH,
                    'packages/covers',
                ),
                'gallery' => array_map(
                    fn (string $photoId): string => $this->storeDemoImage(
                        $operator,
                        $this->unsplash($photoId, 1200),
                        MediaStore::COVER_MAX_WIDTH,
                        'packages/gallery',
                    ),
                    $definition['gallery'],
                ),
                'description' => $definition['description'],
                'itinerary_text' => $definition['itinerary'],
                'inclusions' => $definition['inclusions'],
                'exclusions' => $definition['exclusions'],
                'free_cancellation_hours' => 24,
                'advance_booking_hours' => 12,
                'status' => ListingStatus::Published,
            ]);

            $sync = [];

            foreach ($definition['products'] as $productSlug => $quantity) {
                $sync[$products[$productSlug]->id] = ['quantity_required' => $quantity];
            }

            $package->products()->sync($sync);
            $packages[$definition['slug']] = $package;
        }

        return $packages;
    }

    /**
     * @param  array<string, Package>  $packages
     */
    protected function seedSampleBookings(Operator $operator, array $packages): void
    {
        $snorkelSafari = $packages['three-bay-snorkel-safari'];
        $cliffDay = $packages['west-coast-cliff-day'];

        $guestOne = Guest::query()->create([
            'operator_id' => $operator->id,
            'email' => 'maya.guest@example.com',
            'name' => 'Maya Guest',
            'phone' => '081234567891',
            'notes' => 'Sample guest. Vegetarian lunch.',
        ]);

        $guestTwo = Guest::query()->create([
            'operator_id' => $operator->id,
            'email' => 'liam.guest@example.com',
            'name' => 'Liam Guest',
            'phone' => '081234567892',
            'notes' => 'Sample guest. Repeat look-around booking.',
        ]);

        $firstReservation = Reservation::query()->create([
            'operator_id' => $operator->id,
            'code' => 'RSV-DEMO-001',
            'guest_id' => $guestOne->id,
            'bookable_type' => 'package',
            'bookable_id' => $snorkelSafari->id,
            'guest_name' => 'Maya Guest',
            'guest_contact' => '081234567891',
            'guest_email' => 'maya.guest@example.com',
            'requested_date' => now()->addDays(2)->toDateString(),
            'pax_count' => 2,
            'notes' => 'Vegetarian lunch for two.',
            'terms_snapshot' => $snorkelSafari->generateTermsSnapshot(),
            'status' => ReservationStatus::Confirmed,
            'hold_expires_at' => null,
            'public_token' => Reservation::generateUniquePublicToken(),
        ]);

        Payment::query()->create([
            'reservation_id' => $firstReservation->id,
            'amount' => 1300000.00,
            'gateway' => 'demo',
            'gateway_ref' => 'INV-DEMO-001',
            'split_details' => [
                'commission_rate' => 0.00,
                'platform_commission' => 0.00,
                'operator_amount' => 1300000.00,
            ],
            'status' => PaymentStatus::Paid,
        ]);

        WalletTransaction::query()->create([
            'operator_id' => $operator->id,
            'reservation_id' => $firstReservation->id,
            'type' => WalletTransactionType::BookingEarning,
            'gross_amount' => 1300000.00,
            'fee_amount' => 0.00,
            'net_amount' => 1300000.00,
            'balance_snapshot' => 1300000.00,
            'status' => WalletTransactionStatus::Cleared,
            'available_at' => now(),
            'description' => 'Demo booking #'.$firstReservation->code,
        ]);

        $secondReservation = Reservation::query()->create([
            'operator_id' => $operator->id,
            'code' => 'RSV-DEMO-002',
            'guest_id' => $guestTwo->id,
            'bookable_type' => 'package',
            'bookable_id' => $cliffDay->id,
            'guest_name' => 'Liam Guest',
            'guest_contact' => '081234567892',
            'guest_email' => 'liam.guest@example.com',
            'requested_date' => now()->addDays(6)->toDateString(),
            'pax_count' => 4,
            'notes' => 'Sample anniversary look-around booking.',
            'terms_snapshot' => $cliffDay->generateTermsSnapshot(),
            'status' => ReservationStatus::Confirmed,
            'hold_expires_at' => null,
            'public_token' => Reservation::generateUniquePublicToken(),
        ]);

        Payment::query()->create([
            'reservation_id' => $secondReservation->id,
            'amount' => 3000000.00,
            'gateway' => 'demo',
            'gateway_ref' => 'INV-DEMO-002',
            'split_details' => [
                'commission_rate' => 0.00,
                'platform_commission' => 0.00,
                'operator_amount' => 3000000.00,
            ],
            'status' => PaymentStatus::Paid,
        ]);

        WalletTransaction::query()->create([
            'operator_id' => $operator->id,
            'reservation_id' => $secondReservation->id,
            'type' => WalletTransactionType::BookingEarning,
            'gross_amount' => 3000000.00,
            'fee_amount' => 0.00,
            'net_amount' => 3000000.00,
            'balance_snapshot' => 4300000.00,
            'status' => WalletTransactionStatus::Cleared,
            'available_at' => now(),
            'description' => 'Demo booking #'.$secondReservation->code,
        ]);

        Review::query()->create([
            'operator_id' => $operator->id,
            'reservation_id' => $firstReservation->id,
            'bookable_type' => 'package',
            'bookable_id' => $snorkelSafari->id,
            'rating' => 5,
            'comment' => 'Clear sample review for the snorkel day. The boat was on time and the guide was easy to follow.',
        ]);

        Review::query()->create([
            'operator_id' => $operator->id,
            'reservation_id' => $secondReservation->id,
            'bookable_type' => 'package',
            'bookable_id' => $cliffDay->id,
            'rating' => 5,
            'comment' => 'Clear sample review for the cliff day. Easy pickup and enough time at each stop.',
        ]);
    }

    /**
     * @return list<array{
     *     name: string,
     *     slug: string,
     *     category: string,
     *     location: string,
     *     capacity: int,
     *     price: float,
     *     photo: string,
     *     gallery: list<string>,
     *     description: string,
     *     inclusions: list<string>,
     *     exclusions: list<string>
     * }>
     */
    protected function activityDefinitions(): array
    {
        return [
            [
                'name' => 'Guided Reef Snorkel',
                'slug' => 'guided-reef-snorkel',
                'category' => 'Activity Session',
                'location' => 'Crystal Bay',
                'capacity' => 24,
                'price' => 175000.00,
                'photo' => '1544551763-46a013bb70d5',
                'gallery' => ['1682687220063-4742bd7fd538'],
                'description' => 'A two-hour guided snorkel with gear, a life jacket, and a local spotter.',
                'inclusions' => ['Mask and snorkel', 'Life jacket', 'Guide'],
                'exclusions' => ['Hotel transfer'],
            ],
            [
                'name' => 'Island Fastboat Seat',
                'slug' => 'island-fastboat-seat',
                'category' => 'Day Transport',
                'location' => 'Sanur Harbour',
                'capacity' => 40,
                'price' => 175000.00,
                'photo' => '1544644181-1484b3fdfc62',
                'gallery' => ['1506929562872-bb421503ef21'],
                'description' => 'One-way fastboat seat with port tax and a 20kg bag.',
                'inclusions' => ['Fastboat ticket', 'Port tax'],
                'exclusions' => ['Hotel pickup'],
            ],
            [
                'name' => 'Private Island Car',
                'slug' => 'private-island-car',
                'category' => 'Day Transport',
                'location' => 'Island highlights',
                'capacity' => 8,
                'price' => 650000.00,
                'photo' => '1533473359331-0135ef1b58bf',
                'gallery' => ['1518548419970-58e3b4079ab2'],
                'description' => 'Air-conditioned car and driver for a full day of viewpoints.',
                'inclusions' => ['Private car', 'Driver', 'Fuel and parking'],
                'exclusions' => ['Entry tickets', 'Lunch'],
            ],
            [
                'name' => 'GoPro Underwater Photo',
                'slug' => 'gopro-underwater-photo',
                'category' => 'Add-on Service',
                'location' => 'On the boat',
                'capacity' => 12,
                'price' => 150000.00,
                'photo' => '1527864550417-7fd91fc51a46',
                'gallery' => ['1544551763-46a013bb70d5'],
                'description' => 'A dedicated underwater camera session. Guests keep the memory card.',
                'inclusions' => ['GoPro session', 'Floating grip', '64GB card'],
                'exclusions' => ['Video editing'],
            ],
            [
                'name' => 'Local Guide and Spotter',
                'slug' => 'local-guide-and-spotter',
                'category' => 'Guide Hire',
                'location' => 'Island bays',
                'capacity' => 10,
                'price' => 250000.00,
                'photo' => '1537996194471-e657df975ab4',
                'gallery' => ['1544644181-1484b3fdfc62'],
                'description' => 'Certified local guide for marine encounters, navigation, and guest safety.',
                'inclusions' => ['Certified guide', 'Safety briefing'],
                'exclusions' => ['Guide tip'],
            ],
            [
                'name' => 'Private Speedboat Charter',
                'slug' => 'private-speedboat-charter',
                'category' => 'Day Tour / Trip',
                'location' => 'Island coast',
                'capacity' => 3,
                'price' => 4500000.00,
                'photo' => '1567899378494-47b22a2ae96a',
                'gallery' => ['1540555700478-4be289fbecef'],
                'description' => 'Private boat with skipper, shaded seating, and a cooler for the day.',
                'inclusions' => ['Private boat', 'Skipper and crew', 'Fuel'],
                'exclusions' => ['Meals', 'Drinks'],
            ],
            [
                'name' => 'Paddleboard Session',
                'slug' => 'paddleboard-session',
                'category' => 'Activity Session',
                'location' => 'Calm bay',
                'capacity' => 20,
                'price' => 175000.00,
                'photo' => '1493558103817-58b2924bce98',
                'gallery' => ['1507525428034-b723cf961d3e'],
                'description' => 'Two-hour stand-up paddle session with a leash, life jacket, and instructor.',
                'inclusions' => ['Board and paddle', 'Life jacket', 'Instructor'],
                'exclusions' => ['Personal photos'],
            ],
        ];
    }

    /**
     * @return list<array{
     *     title: string,
     *     slug: string,
     *     category: string,
     *     location: string,
     *     price: float,
     *     photo: string,
     *     gallery: list<string>,
     *     description: string,
     *     itinerary: string,
     *     inclusions: list<string>,
     *     exclusions: list<string>,
     *     products: array<string, int>
     * }>
     */
    protected function packageDefinitions(): array
    {
        return [
            [
                'title' => 'Three-Bay Snorkel Safari',
                'slug' => 'three-bay-snorkel-safari',
                'category' => 'Day Experience',
                'location' => 'Three island bays',
                'price' => 650000.00,
                'photo' => '1544644181-1484b3fdfc62',
                'gallery' => ['1544551763-46a013bb70d5', '1682687220063-4742bd7fd538'],
                'description' => 'A full-day boat trip with three snorkel stops, lunch, and a guide.',
                'itinerary' => "07:30 — Harbour check-in\n08:00 — Boat out\n09:00 — First bay\n12:30 — Lunch\n16:00 — Return",
                'inclusions' => ['Return boat', 'Snorkel gear', 'Lunch and water', 'Guide'],
                'exclusions' => ['Hotel transfer', 'Island tax'],
                'products' => [
                    'island-fastboat-seat' => 2,
                    'guided-reef-snorkel' => 1,
                    'local-guide-and-spotter' => 1,
                ],
            ],
            [
                'title' => 'West Coast Cliff Day',
                'slug' => 'west-coast-cliff-day',
                'category' => 'Island Tour',
                'location' => 'West coast viewpoints',
                'price' => 750000.00,
                'photo' => '1518548419970-58e3b4079ab2',
                'gallery' => ['1573790387438-4da905039392', '1537996194471-e657df975ab4'],
                'description' => 'Boat across, then a private car to the main cliff viewpoints and a swim stop.',
                'itinerary' => "07:00 — Boat out\n09:30 — First viewpoint\n12:00 — Lunch\n15:30 — Swim stop\n17:00 — Return boat",
                'inclusions' => ['Return boat', 'Private car', 'Lunch'],
                'exclusions' => ['Souvenirs'],
                'products' => [
                    'island-fastboat-seat' => 2,
                    'private-island-car' => 1,
                ],
            ],
            [
                'title' => 'Private Sunset Cruise',
                'slug' => 'private-sunset-cruise',
                'category' => 'Private Tour',
                'location' => 'Island coast',
                'price' => 3500000.00,
                'photo' => '1567899378494-47b22a2ae96a',
                'gallery' => ['1540555700478-4be289fbecef', '1507525428034-b723cf961d3e'],
                'description' => 'Private afternoon boat, a snorkel stop, and sunset on the way home.',
                'itinerary' => "13:30 — Private boarding\n14:30 — Snorkel stop\n17:45 — Sunset cruise\n19:00 — Return",
                'inclusions' => ['Private boat', 'Snorkel gear', 'Fruit platter'],
                'exclusions' => ['Alcohol'],
                'products' => [
                    'private-speedboat-charter' => 1,
                    'guided-reef-snorkel' => 4,
                    'gopro-underwater-photo' => 1,
                ],
            ],
            [
                'title' => 'East Coast Beach Day',
                'slug' => 'east-coast-beach-day',
                'category' => 'Island Tour',
                'location' => 'East coast beaches',
                'price' => 800000.00,
                'photo' => '1589308078059-be1415eab4c3',
                'gallery' => ['1552465011-b4e21bf6e79a', '1507525428034-b723cf961d3e'],
                'description' => 'White-sand beaches, a treehouse viewpoint, and a hilltop lunch.',
                'itinerary' => "06:30 — Early boat\n09:00 — Viewpoint\n11:00 — Beach swim\n13:00 — Lunch\n16:45 — Return boat",
                'inclusions' => ['Return boat', 'Private car', 'Lunch'],
                'exclusions' => ['Optional cliff swing'],
                'products' => [
                    'island-fastboat-seat' => 2,
                    'private-island-car' => 1,
                ],
            ],
            [
                'title' => 'Manta Point Dive Day',
                'slug' => 'manta-point-dive-day',
                'category' => 'Scuba Diving',
                'location' => 'Manta Point',
                'price' => 1250000.00,
                'photo' => '1682687220063-4742bd7fd538',
                'gallery' => ['1544551763-46a013bb70d5', '1544644181-1484b3fdfc62'],
                'description' => 'Two-tank boat dive for certified divers at the manta cleaning station.',
                'itinerary' => "07:45 — Gear check\n08:30 — Dive 1\n12:00 — Dive 2\n13:30 — Lunch and return",
                'inclusions' => ['Two boat dives', 'Tanks and weights', 'Lunch'],
                'exclusions' => ['Full gear rental'],
                'products' => [
                    'local-guide-and-spotter' => 1,
                    'guided-reef-snorkel' => 1,
                ],
            ],
            [
                'title' => 'Full Island Discovery',
                'slug' => 'full-island-discovery',
                'category' => 'Island Tour',
                'location' => 'East and west highlights',
                'price' => 950000.00,
                'photo' => '1577717903315-1691ae25ab3f',
                'gallery' => ['1518548419970-58e3b4079ab2', '1589308078059-be1415eab4c3'],
                'description' => 'One private car day covering the main east and west viewpoints.',
                'itinerary' => "06:30 — Boat out\n07:45 — East coast\n13:00 — West coast\n16:30 — Return boat",
                'inclusions' => ['Return boat', 'Private car', 'Entry tickets', 'Lunch'],
                'exclusions' => ['Snacks'],
                'products' => [
                    'island-fastboat-seat' => 2,
                    'private-island-car' => 1,
                ],
            ],
            [
                'title' => 'VIP Private Island Day',
                'slug' => 'vip-private-island-day',
                'category' => 'Private Tour',
                'location' => 'Four island bays',
                'price' => 4800000.00,
                'photo' => '1500530855697-b586d89ba3ee',
                'gallery' => ['1567899378494-47b22a2ae96a', '1493558103817-58b2924bce98'],
                'description' => 'Private boat, snorkel, paddle, and camera service for a small group.',
                'itinerary' => "08:30 — Private boarding\n09:15 — First bay\n12:30 — Beach lunch\n14:00 — Paddle stop\n16:00 — Return",
                'inclusions' => ['Private boat', 'Snorkel gear', 'Paddleboards', 'Lunch'],
                'exclusions' => ['Premium drinks'],
                'products' => [
                    'private-speedboat-charter' => 1,
                    'guided-reef-snorkel' => 6,
                    'gopro-underwater-photo' => 1,
                    'paddleboard-session' => 2,
                ],
            ],
        ];
    }

    protected function unsplash(string $photoId, int $width = 1200): string
    {
        return 'https://images.unsplash.com/photo-'.$photoId.'?auto=format&fit=crop&w='.$width.'&q=80';
    }

    protected function shouldDownloadDemoImages(): bool
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: '';

        return $env !== 'testing';
    }

    /**
     * Download a demo photo, resize it, and store it as WebP under the operator folder.
     */
    protected function storeDemoImage(Operator $operator, string $url, int $maxWidth = MediaStore::COVER_MAX_WIDTH, string $within = 'catalog'): string
    {
        $media = app(MediaStore::class);
        $directory = $media->directoryFor($operator, $within);

        if ($this->shouldDownloadDemoImages()) {
            $response = Http::timeout(20)
                ->connectTimeout(5)
                ->withUserAgent('TravelEngine Demo Seeder')
                ->get($url);

            if ($response->successful() && str_starts_with(strtolower((string) $response->header('Content-Type')), 'image/')) {
                return $media->storeImageContents($response->body(), $directory, $maxWidth);
            }
        }

        return $media->storeImageContents($this->placeholderJpeg(), $directory, $maxWidth);
    }

    protected function placeholderJpeg(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $image = imagecreatetruecolor(1200, 800);
            $background = imagecolorallocate($image, 15, 118, 110);
            imagefilledrectangle($image, 0, 0, 1199, 799, $background);
            ob_start();
            imagejpeg($image, quality: 80);
            imagedestroy($image);

            return (string) ob_get_clean();
        }

        return (string) base64_decode(''
            .'/9j/4AAQSkZJRgABAQAAAQABAAD/2wAAAAD/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==',
            true);
    }
}
