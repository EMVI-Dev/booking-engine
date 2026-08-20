<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Models\Agent;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\DokuPaymentService;
use App\Services\DomainResolverService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function __construct(
        protected DomainResolverService $domainResolver
    ) {}

    /**
     * Resolve the active Agent from request attributes, container, or domain resolver.
     */
    protected function resolveCurrentAgent(Request $request): ?Agent
    {
        /** @var Agent|null $agent */
        $agent = $request->attributes->get('current_agent');

        if ($agent instanceof Agent) {
            return $agent;
        }

        if (app()->bound('current_agent')) {
            $instance = app('current_agent');
            if ($instance instanceof Agent) {
                return $instance;
            }
        }

        return $this->domainResolver->resolveAgent($request);
    }

    /**
     * Display the storefront home page or central platform welcome page.
     */
    public function index(Request $request): View
    {
        $agent = $this->resolveCurrentAgent($request);

        // If no agent domain is active, render central platform landing page
        if (! $agent) {
            return view('welcome');
        }

        // Ordered by most booked (reservations count) then latest
        $packages = $agent->packages()
            ->where('status', ListingStatus::Published)
            ->with(['products'])
            ->withCount('reservations')
            ->orderByDesc('reservations_count')
            ->latest()
            ->get();

        $standaloneProducts = $agent->products()
            ->where('status', ListingStatus::Published)
            ->where('sellable_standalone', true)
            ->withCount('reservations')
            ->orderByDesc('reservations_count')
            ->latest()
            ->get();

        $reviews = $agent->reviews()
            ->with('bookable')
            ->latest()
            ->take(6)
            ->get();

        return view('storefront.index', [
            'agent' => $agent,
            'packages' => $packages,
            'products' => $standaloneProducts,
            'standaloneProducts' => $standaloneProducts,
            'reviews' => $reviews,
        ]);
    }

    /**
     * Display the complete catalog of all tour packages with search & filters.
     */
    public function allPackages(Request $request): View
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        $search = (string) $request->query('search', '');
        $category = (string) $request->query('category', '');

        $packages = $agent->packages()
            ->where('status', ListingStatus::Published)
            ->with(['products'])
            ->withCount('reservations')
            ->when($search, fn ($q) => $q->where(fn ($sub) => $sub->where('title', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('reservations_count')
            ->latest()
            ->get();

        $categories = $agent->packages()
            ->where('status', ListingStatus::Published)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return view('storefront.packages', [
            'agent' => $agent,
            'packages' => $packages,
            'categories' => $categories,
            'selectedCategory' => $category,
            'search' => $search,
        ]);
    }

    /**
     * Display the complete catalog of all standalone activities, services & rentals.
     */
    public function allProducts(Request $request): View
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        $search = (string) $request->query('search', '');
        $category = (string) $request->query('category', '');

        $products = $agent->products()
            ->where('status', ListingStatus::Published)
            ->where('sellable_standalone', true)
            ->withCount('reservations')
            ->when($search, fn ($q) => $q->where(fn ($sub) => $sub->where('name', 'like', "%{$search}%")->orWhere('description', 'like', "%{$search}%")->orWhere('location', 'like', "%{$search}%")))
            ->when($category, fn ($q) => $q->where('category', $category))
            ->orderByDesc('reservations_count')
            ->latest()
            ->get();

        $categories = $agent->products()
            ->where('status', ListingStatus::Published)
            ->where('sellable_standalone', true)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category');

        return view('storefront.products', [
            'agent' => $agent,
            'products' => $products,
            'categories' => $categories,
            'selectedCategory' => $category,
            'search' => $search,
        ]);
    }

    /**
     * Display a specific tour package on the agent storefront.
     */
    public function showPackage(Request $request, string $slug): View
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        $package = $agent->packages()
            ->where('slug', $slug)
            ->where('status', ListingStatus::Published)
            ->with(['products'])
            ->firstOrFail();

        return view('storefront.package', [
            'agent' => $agent,
            'package' => $package,
        ]);
    }

    /**
     * Display a specific standalone product on the agent storefront.
     */
    public function showProduct(Request $request, string $slug): View
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        $product = $agent->products()
            ->where('slug', $slug)
            ->where('status', ListingStatus::Published)
            ->where('sellable_standalone', true)
            ->firstOrFail();

        return view('storefront.product', [
            'agent' => $agent,
            'product' => $product,
        ]);
    }

    /**
     * Display agent storefront booking terms & policies.
     */
    public function showTerms(Request $request): View
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        return view('storefront.terms', [
            'agent' => $agent,
        ]);
    }

    /**
     * Simulated DOKU Sandbox payment gateway screen for testing.
     */
    public function simulatePayment(Request $request): View
    {
        $reservationId = (string) $request->query('reservation');
        $paymentId = (string) $request->query('payment');

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->with(['agent', 'bookable'])->findOrFail($reservationId);
        /** @var Payment $payment */
        $payment = Payment::query()->findOrFail($paymentId);

        return view('storefront.payment-simulate', [
            'reservation' => $reservation,
            'payment' => $payment,
            'agent' => $reservation->agent,
        ]);
    }

    /**
     * Confirm simulated payment and trigger webhook callback.
     */
    public function confirmSimulatedPayment(Request $request, DokuPaymentService $paymentService): RedirectResponse
    {
        $reservationId = (string) $request->input('reservation_id');
        $status = (string) $request->input('status', 'SUCCESS');

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->findOrFail($reservationId);
        /** @var Payment|null $payment */
        $payment = $reservation->latestPayment;

        if ($payment) {
            $paymentService->processNotification([
                'order' => ['invoice_number' => $payment->gateway_ref],
                'transaction' => ['status' => $status],
            ]);
        }

        return redirect()->route('storefront.reservation.receipt', $reservation->id);
    }

    /**
     * Show guest reservation confirmation receipt.
     */
    public function showReceipt(Reservation $reservation): View
    {
        $reservation->load(['agent', 'bookable', 'latestPayment']);

        return view('storefront.confirmation', [
            'reservation' => $reservation,
            'agent' => $reservation->agent,
        ]);
    }

    /**
     * Generate dynamic robots.txt optimized for search engines & AI crawlers.
     */
    public function robots(Request $request): Response
    {
        $agent = $this->resolveCurrentAgent($request);
        $baseUrl = $request->getSchemeAndHttpHost();

        $content = "User-agent: *\n";
        $content .= "Allow: /\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /dashboard/\n";
        $content .= "Disallow: /checkout/\n";
        $content .= "Disallow: /api/\n";
        $content .= "Disallow: /livewire/\n\n";

        $content .= "# AI Discovery & LLM Crawlers Directives\n";
        $content .= "User-agent: GPTBot\nAllow: /\n";
        $content .= "User-agent: ChatGPT-User\nAllow: /\n";
        $content .= "User-agent: PerplexityBot\nAllow: /\n";
        $content .= "User-agent: ClaudeBot\nAllow: /\n";
        $content .= "User-agent: Claude-Web\nAllow: /\n";
        $content .= "User-agent: Google-Extended\nAllow: /\n";
        $content .= "User-agent: Applebot-Extended\nAllow: /\n";
        $content .= "User-agent: cohere-ai\nAllow: /\n";
        $content .= "User-agent: anthropic-ai\nAllow: /\n\n";

        $content .= "Sitemap: {$baseUrl}/sitemap.xml\n";
        if ($agent) {
            $content .= "# AI Discovery Files\n";
            $content .= "llms-txt: {$baseUrl}/llms.txt\n";
            $content .= "llms-full: {$baseUrl}/llms-full.txt\n";
        }

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Generate dynamic sitemap.xml for the current agent domain or platform.
     */
    public function sitemap(Request $request): Response
    {
        $agent = $this->resolveCurrentAgent($request);
        $baseUrl = $request->getSchemeAndHttpHost();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        // Homepage
        $xml .= "  <url>\n    <loc>{$baseUrl}</loc>\n    <changefreq>daily</changefreq>\n    <priority>1.0</priority>\n  </url>\n";

        if ($agent) {
            // Packages Catalog
            $xml .= "  <url>\n    <loc>{$baseUrl}/tours</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.9</priority>\n  </url>\n";
            // Standalone Products Catalog
            $xml .= "  <url>\n    <loc>{$baseUrl}/services</loc>\n    <changefreq>daily</changefreq>\n    <priority>0.9</priority>\n  </url>\n";
            // Terms
            $xml .= "  <url>\n    <loc>{$baseUrl}/terms</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.5</priority>\n  </url>\n";

            // Individual Packages
            $packages = $agent->packages()->where('status', ListingStatus::Published)->get();
            foreach ($packages as $pkg) {
                $lastmod = $pkg->updated_at?->toIso8601String() ?? now()->toIso8601String();
                $xml .= "  <url>\n    <loc>{$baseUrl}/packages/{$pkg->slug}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
            }

            // Individual Standalone Products
            $products = $agent->products()->where('status', ListingStatus::Published)->where('sellable_standalone', true)->get();
            foreach ($products as $prod) {
                $lastmod = $prod->updated_at?->toIso8601String() ?? now()->toIso8601String();
                $xml .= "  <url>\n    <loc>{$baseUrl}/products/{$prod->slug}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>0.8</priority>\n  </url>\n";
            }
        }

        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * Provide llms.txt standard summary for AI Search & Agent discovery.
     */
    public function llmsTxt(Request $request): Response
    {
        $agent = $this->resolveCurrentAgent($request);
        $baseUrl = $request->getSchemeAndHttpHost();

        if (! $agent) {
            $content = "# Direct Booking Engine\n\nPlatform for direct verified tour operator storefronts.\n";

            return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $packages = $agent->packages()->where('status', ListingStatus::Published)->withCount('reservations')->orderByDesc('reservations_count')->get();
        $products = $agent->products()->where('status', ListingStatus::Published)->where('sellable_standalone', true)->withCount('reservations')->orderByDesc('reservations_count')->get();

        $content = "# {$agent->name} - Direct Tour & Activity Booking Portal\n\n";
        $content .= '> '.($agent->bio ?: 'Official direct operator for boat tours, island expeditions, snorkeling trips, and equipment rentals.')."\n\n";

        $content .= "## Operator Overview\n";
        $content .= "- **Operator Name**: {$agent->name}\n";
        $content .= "- **Storefront URL**: {$baseUrl}\n";
        $content .= "- **Payment Currency**: IDR (Indonesian Rupiah)\n";
        $content .= "- **Direct Booking System**: Instant 30-minute availability holds with automated payment processing\n";
        if ($agent->contact_whatsapp) {
            $content .= "- **WhatsApp Contact**: {$agent->contact_whatsapp}\n";
        }
        $content .= "\n";

        $content .= "## Tour Packages & Island Trips\n";
        if ($packages->isEmpty()) {
            $content .= "- No tour packages currently published.\n";
        } else {
            foreach ($packages as $pkg) {
                $priceFormatted = number_format((float) $pkg->price, 0, ',', '.');
                $inclusions = ! empty($pkg->inclusions) ? implode(', ', array_slice($pkg->inclusions, 0, 4)) : 'Standard trip amenities';
                $content .= "- [{$pkg->title}]({$baseUrl}/packages/{$pkg->slug}): Rp {$priceFormatted} / person. Location: ".($pkg->location ?? 'Bali / Nusa Penida').". Inclusions: {$inclusions}. Free cancellation: {$pkg->free_cancellation_hours}h.\n";
            }
        }
        $content .= "\n";

        $content .= "## Single Activities, Transfers & Equipment Rentals\n";
        if ($products->isEmpty()) {
            $content .= "- No standalone activities currently published.\n";
        } else {
            foreach ($products as $prod) {
                $priceFormatted = number_format((float) $prod->price, 0, ',', '.');
                $capacity = $prod->capacity_per_day ? "Capacity: {$prod->capacity_per_day}/day" : 'Available daily';
                $content .= "- [{$prod->name}]({$baseUrl}/products/{$prod->slug}): Rp {$priceFormatted}. Category: ".($prod->category ?? 'Service').". {$capacity}.\n";
            }
        }
        $content .= "\n";

        $content .= "## Policies & Booking Details\n";
        $content .= "- [Terms & Cancellation Policies]({$baseUrl}/terms)\n";
        $content .= "- [Complete Detailed Knowledge Base (LLMs Full)]({$baseUrl}/llms-full.txt)\n";

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * Provide comprehensive llms-full.txt knowledge document for deep AI reasoning & citations.
     */
    public function llmsFullTxt(Request $request): Response
    {
        $agent = $this->resolveCurrentAgent($request);
        $baseUrl = $request->getSchemeAndHttpHost();

        if (! $agent) {
            $content = "# Direct Booking Engine\n\nPlatform for direct verified tour operator storefronts.\n";

            return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $packages = $agent->packages()->where('status', ListingStatus::Published)->with(['products'])->get();
        $products = $agent->products()->where('status', ListingStatus::Published)->where('sellable_standalone', true)->get();

        $content = "# {$agent->name} Comprehensive Operator Knowledge Base\n\n";
        $content .= "## About {$agent->name}\n";
        $content .= ($agent->bio ?: 'Official direct operator providing authenticated, high-quality marine expeditions and tours.')."\n\n";

        $content .= "## Direct Booking Architecture & Reservation Lifecycle\n";
        $content .= "1. **Discovery & Selection**: Guests select a date and party size (pax).\n";
        $content .= "2. **Atomic Inventory Verification**: The engine verifies real-time slot and equipment availability against {$agent->name}'s capacity records.\n";
        $content .= "3. **Instant 30-Minute Hold**: Inventory is reserved for 30 minutes with a frozen pricing snapshot and strict terms lock.\n";
        $content .= "4. **Automated Payment Settlement**: Payments are routed securely via DOKU Payment Gateway (Virtual Account, QRIS, Credit Card).\n";
        $content .= "5. **Instant Confirmation**: Digital booking receipt with QR confirmation code is generated immediately.\n\n";

        $content .= "## Detailed Tour Packages Catalog\n";
        foreach ($packages as $pkg) {
            $priceFormatted = number_format((float) $pkg->price, 0, ',', '.');
            $content .= "### {$pkg->title}\n";
            $content .= "- **URL**: {$baseUrl}/packages/{$pkg->slug}\n";
            $content .= "- **Price**: Rp {$priceFormatted} / person\n";
            $content .= '- **Location**: '.($pkg->location ?? 'Bali / Nusa Penida')."\n";
            $content .= '- **Category**: '.($pkg->category ?? 'Tour Package')."\n";
            $content .= "- **Advance Booking Required**: {$pkg->advance_booking_hours} hours prior to departure\n";
            $content .= "- **Free Cancellation**: Up to {$pkg->free_cancellation_hours} hours before departure\n";
            if ($pkg->description) {
                $content .= "- **Overview**: {$pkg->description}\n";
            }
            if ($pkg->itinerary_text) {
                $content .= "- **Itinerary**: {$pkg->itinerary_text}\n";
            }
            if (! empty($pkg->inclusions)) {
                $content .= '- **Inclusions**: '.implode(', ', $pkg->inclusions)."\n";
            }
            if (! empty($pkg->exclusions)) {
                $content .= '- **Exclusions**: '.implode(', ', $pkg->exclusions)."\n";
            }
            if ($pkg->cancellation_terms) {
                $content .= "- **Cancellation Terms**: {$pkg->cancellation_terms}\n";
            }
            $content .= "\n";
        }

        $content .= "## Detailed Standalone Activities & Equipment Inventory\n";
        foreach ($products as $prod) {
            $priceFormatted = number_format((float) $prod->price, 0, ',', '.');
            $content .= "### {$prod->name}\n";
            $content .= "- **URL**: {$baseUrl}/products/{$prod->slug}\n";
            $content .= "- **Price**: Rp {$priceFormatted}\n";
            $content .= '- **Category**: '.($prod->category ?? 'Equipment & Activities')."\n";
            $content .= '- **Daily Maximum Capacity**: '.($prod->capacity_per_day ? "{$prod->capacity_per_day} units/guests" : 'Unlimited')."\n";
            $content .= "- **Advance Booking Required**: {$prod->advance_booking_hours} hours\n";
            $content .= "- **Free Cancellation**: {$prod->free_cancellation_hours} hours\n";
            if ($prod->description) {
                $content .= "- **Overview**: {$prod->description}\n";
            }
            if (! empty($prod->inclusions)) {
                $content .= '- **Inclusions**: '.implode(', ', $prod->inclusions)."\n";
            }
            $content .= "\n";
        }

        return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
