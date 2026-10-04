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
use App\Models\OperatorGalleryPhoto;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Services\DomainResolverService;
use App\Services\MediaStore;
use App\Services\StorefrontGalleryService;
use Illuminate\Database\Seeder;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class DemoOperatorSeeder extends Seeder
{
    /**
     * Seed the public demo operator. Safe to re-run (demo:refresh): only this operator is wiped.
     * Media storage is checked first, so a broken R2 setup stops here before anything is wiped.
     */
    public function run(): void
    {
        MediaStore::verifyWritable();
        Plan::ensureDefaultPlans();

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

    /** @var list<string> */
    protected array $wipedOperatorIds = [];

    protected function wipeDemoOperators(): void
    {
        $media = app(MediaStore::class);

        $ids = Operator::query()
            ->where(function ($query): void {
                $query->where('is_demo', true)
                    ->orWhere('slug', config('demo.slug', 'demo'));
            })
            ->pluck('id');

        $this->wipedOperatorIds = $ids->all();

        foreach ($ids as $id) {
            $media->deleteDirectory($media->directoryFor($id));
        }

        $media->deleteDirectory('demo');

        Operator::query()->whereIn('id', $ids)->delete();
    }

    protected function seedDemoOperator(): void
    {
        $email = (string) config('demo.email', 'demo@travelengine.id');
        $password = config('demo.password');
        $name = (string) config('demo.name', 'Demo Tours');
        $slug = (string) config('demo.slug', 'demo');

        if (! is_string($password) || $password === '') {
            if (! app()->environment(['local', 'testing'])) {
                throw new RuntimeException('Set DEMO_OPERATOR_PASSWORD before seeding the demo operator outside local and testing.');
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
            'terms_and_conditions' => "1. This is a demo storefront. Guests cannot pay.\n2. The catalog and bookings reset every day.\n3. Nothing here moves real money.",
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
                'marketing' => [
                    'review_url' => 'https://travelengine.id',
                ],
                'storefront' => [
                    'allow_standalone_products' => true,
                    'show_inclusions_preview' => true,
                    'booking_confirmation_mode' => 'automatic',
                    'hero_headline' => 'See how a tour operator looks on TravelEngine',
                    'hero_tagline' => 'Browse trips and the operator desk. Checkout stays off so nothing is charged.',
                ],
            ],
        ]);

        $logoPath = $this->storeDemoImage($operator, $this->unsplash('1518065896235-a4c93e088e7a', 400), MediaStore::LOGO_MAX_WIDTH, 'brand');
        $bannerPath = $this->storeDemoImage($operator, $this->unsplash('1507525428034-b723cf961d3e', 1600), MediaStore::COVER_MAX_WIDTH, 'brand');
        // Store paths only; URLs are built from the media disk when shown.
        $operator->update([
            'logo_path' => $logoPath,
            'banner_path' => $bannerPath,
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

        if ($platformDomain !== 'travelengine.id') {
            OperatorDomain::query()->firstOrCreate(
                ['domain' => $slug.'.travelengine.id'],
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
        $this->seedGalleryPhotos($operator);
        $this->migrateAdminSessions($operator);
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
            'vendor_token' => Reservation::generateUniqueVendorToken(),
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
            'vendor_token' => Reservation::generateUniqueVendorToken(),
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
                'photo' => '1708649290066-5f617003b93f',
                'gallery' => ['1437622368342-7a3d73a34c8f'],
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
                'photo' => '1552160757-52790c6f4faf',
                'gallery' => ['1528719953625-3e95efad84da'],
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
                'gallery' => ['1720670272553-d352388d54d0'],
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
                'photo' => '1682687982502-1529b3b33f85',
                'gallery' => ['1602101319087-18d00e6109c0'],
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
                'photo' => '1654414882149-8417f5de3a63',
                'gallery' => ['1539635278303-d4002c07eae3'],
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
                'photo' => '1575224639406-b218af1ee31e',
                'gallery' => ['1567899378494-47b22a2ae96a'],
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
                'photo' => '1526188717906-ab4a2f949f26',
                'gallery' => ['1582391564016-801999ec01d1'],
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
                'photo' => '1682686581663-179efad3cd2f',
                'gallery' => ['1707327956851-30a531b70cda', '1589634749362-a8ef3056cbe9'],
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
                'photo' => '1566987827971-f2c40e748a54',
                'gallery' => ['1604500693431-647f9e76dafc', '1685521298875-40e5bb0ec0ed'],
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
                'photo' => '1559494007-9f5847c49d94',
                'gallery' => ['1783255166377-86fa436a2d7a', '1503803548695-c2a7b4a5b875'],
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
                'photo' => '1604500943879-80a4da030905',
                'gallery' => ['1644027621303-238332ad53f4', '1634337385991-9c28ad464e88'],
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
                'photo' => '1618265909156-0507770ef0d0',
                'gallery' => ['1544551763-46a013bb70d5', '1682687220063-4742bd7fd538'],
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
                'photo' => '1550664255-94d114340500',
                'gallery' => ['1770838126263-9621f332c0d4', '1544644181-1484b3fdfc62'],
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
                'photo' => '1702564502101-ff72bea956e9',
                'gallery' => ['1575224639551-12afa3e701d9', '1588604079477-8d41a2b025d4'],
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

    protected function seedGalleryPhotos(Operator $operator): void
    {
        foreach ($this->galleryPhotoDefinitions() as $index => $item) {
            $path = $this->storeDemoImage(
                $operator,
                $this->unsplash($item['photo'], 1600),
                StorefrontGalleryService::MAX_WIDTH,
                'gallery',
            );

            OperatorGalleryPhoto::query()->create([
                'operator_id' => $operator->id,
                'path' => $path,
                'caption' => $item['caption'],
                'sort_order' => $index + 1,
            ]);
        }
    }

    /**
     * @return list<array{photo: string, caption: string}>
     */
    protected function galleryPhotoDefinitions(): array
    {
        return [
            [
                'photo' => '1507525428034-b723cf961d3e',
                'caption' => 'Morning stillness at Crystal Bay',
            ],
            [
                'photo' => '1708649290066-5f617003b93f',
                'caption' => 'Snorkeling with vibrant coral and marine life',
            ],
            [
                'photo' => '1566987827971-f2c40e748a54',
                'caption' => 'Dramatic coastal cliffs of Kelingking',
            ],
            [
                'photo' => '1618265909156-0507770ef0d0',
                'caption' => 'Swimming alongside majestic manta rays',
            ],
            [
                'photo' => '1503803548695-c2a7b4a5b875',
                'caption' => 'Golden hour over the western ocean horizon',
            ],
            [
                'photo' => '1544644181-1484b3fdfc62',
                'caption' => 'Cultural sanctuary amidst lush tropical hills',
            ],
            [
                'photo' => '1552160757-52790c6f4faf',
                'caption' => 'Private speedboat heading out across the bay',
            ],
            [
                'photo' => '1526188717906-ab4a2f949f26',
                'caption' => 'Paddleboarding in a calm turquoise lagoon',
            ],
            [
                'photo' => '1555400038-63f5ba517a47',
                'caption' => 'Emerald rice terrace trek in early morning light',
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
     * Download or load cached demo photo, resize it, and store it as WebP under the operator folder.
     * In local / development environments, raw downloaded images are cached locally on disk
     * (in storage/app/demo-cache/) so subsequent seeds are instant, 100% local, and offline-safe.
     */
    protected function storeDemoImage(Operator $operator, string $url, int $maxWidth = MediaStore::COVER_MAX_WIDTH, string $within = 'catalog'): string
    {
        $media = app(MediaStore::class);
        $directory = $media->directoryFor($operator, $within);
        $contents = $this->resolveDemoImageContents($url);

        return $media->storeImageContents($contents, $directory, $maxWidth);
    }

    protected function resolveDemoImageContents(string $url): string
    {
        $cacheFile = $this->localDemoImageCachePath($url);

        if ($cacheFile !== null && file_exists($cacheFile) && filesize($cacheFile) > 0) {
            $cached = @file_get_contents($cacheFile);
            if ($cached !== false && $cached !== '') {
                return $cached;
            }
        }

        if ($this->shouldDownloadDemoImages()) {
            try {
                $response = Http::timeout(20)
                    ->connectTimeout(5)
                    ->withUserAgent('TravelEngine Demo Seeder')
                    ->get($url);

                if ($response->successful() && str_starts_with(strtolower((string) $response->header('Content-Type')), 'image/')) {
                    $body = $response->body();

                    if ($cacheFile !== null) {
                        @file_put_contents($cacheFile, $body);
                    }

                    return $body;
                }
            } catch (ConnectionException) {
                // Outbound failure: fall back to placeholder
            }
        }

        return $this->placeholderJpeg();
    }

    protected function localDemoImageCachePath(string $url): ?string
    {
        $cacheDir = storage_path('app/demo-cache');

        if (! is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        if (! is_dir($cacheDir) || ! is_writable($cacheDir)) {
            return null;
        }

        return $cacheDir.'/'.md5($url).'.jpg';
    }

    protected function migrateAdminSessions(Operator $newOperator): void
    {
        if (empty($this->wipedOperatorIds) || ! Schema::hasTable('sessions')) {
            return;
        }

        DB::table('sessions')->get()->each(function ($session) use ($newOperator): void {
            $payload = @base64_decode((string) $session->payload, true);
            if (! $payload) {
                return;
            }

            $data = json_decode($payload, true);
            if (! is_array($data)) {
                return;
            }

            if (isset($data[User::IMPERSONATION_SESSION_KEY]) && in_array($data[User::IMPERSONATION_SESSION_KEY], $this->wipedOperatorIds, true)) {
                $data[User::IMPERSONATION_SESSION_KEY] = $newOperator->id;
                $data[User::IMPERSONATION_STARTED_KEY] = now()->getTimestamp();
                DB::table('sessions')->where('id', $session->id)->update([
                    'payload' => base64_encode((string) json_encode($data)),
                ]);
            }
        });
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

        return (string) base64_decode(
            ''
                .'/9j/4AAQSkZJRgABAQAAAQABAAD/2wAAAAD/wAALCAABAAEBAREA/8QAFAABAAAAAAAAAAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q==',
            true
        );
    }
}
