<?php

namespace App\Http\Controllers;

use App\Contracts\Bookable;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\CapacityUnavailableException;
use App\Models\Operator;
use App\Models\Payment;
use App\Models\Reservation;
use App\Services\CapacityService;
use App\Services\DokuPaymentService;
use App\Services\DomainResolverService;
use App\Services\GuestCancellationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    /**
     * Listings shown per page on the /tours and /services catalog pages.
     */
    private const CATALOG_PAGE_SIZE = 12;

    public function __construct(
        protected DomainResolverService $domainResolver
    ) {}

    /**
     * Resolve the active Operator from request attributes, container, or domain resolver.
     */
    protected function resolveCurrentOperator(Request $request): ?Operator
    {
        /** @var Operator|null $operator */
        $operator = $request->attributes->get('current_operator') ?? $request->attributes->get('current_agent');

        if ($operator instanceof Operator) {
            return $operator;
        }

        if (app()->bound('current_operator')) {
            $instance = app('current_operator');
            if ($instance instanceof Operator) {
                return $instance;
            }
        }

        return $this->domainResolver->resolveOperator($request);
    }

    /**
     * @deprecated Use resolveCurrentOperator() instead.
     */
    protected function resolveCurrentAgent(Request $request): ?Operator
    {
        return $this->resolveCurrentOperator($request);
    }

    /**
     * Check if operator storefront is active or return suspended/pending response.
     */
    protected function checkOperatorStatus(?Operator $agent): ?\Symfony\Component\HttpFoundation\Response
    {
        if (! $agent) {
            return null;
        }

        if ($agent->status === OperatorStatus::Suspended) {
            return response()->view('storefront.suspended', ['agent' => $agent], 403);
        }

        if ($agent->status === OperatorStatus::Pending) {
            return response()->view('storefront.pending', [
                'agent' => $agent,
                'reason' => 'pending',
            ], 503);
        }

        if (! $agent->isStorefrontSetupComplete()) {
            return response()->view('storefront.pending', [
                'agent' => $agent,
                'reason' => 'setup',
            ], 503);
        }

        return null;
    }

    /**
     * Display the storefront home page or central platform welcome page.
     */
    public function index(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        $agent = $this->resolveCurrentAgent($request);

        // If no agent domain is active, render central platform landing page
        if (! $agent) {
            return view('welcome');
        }

        if ($statusResponse = $this->checkOperatorStatus($agent)) {
            return $statusResponse;
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
    public function allPackages(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        if ($statusResponse = $this->checkOperatorStatus($agent)) {
            return $statusResponse;
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
            ->paginate(self::CATALOG_PAGE_SIZE)
            ->withQueryString();

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
    public function allProducts(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        if ($statusResponse = $this->checkOperatorStatus($agent)) {
            return $statusResponse;
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
            ->paginate(self::CATALOG_PAGE_SIZE)
            ->withQueryString();

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
    public function showPackage(Request $request, string $slug): View|\Symfony\Component\HttpFoundation\Response
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        if ($statusResponse = $this->checkOperatorStatus($agent)) {
            return $statusResponse;
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
    public function showProduct(Request $request, string $slug): View|\Symfony\Component\HttpFoundation\Response
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        if ($statusResponse = $this->checkOperatorStatus($agent)) {
            return $statusResponse;
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
    public function showTerms(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        $agent = $this->resolveCurrentAgent($request);

        if (! $agent) {
            abort(404);
        }

        if ($statusResponse = $this->checkOperatorStatus($agent)) {
            return $statusResponse;
        }

        return view('storefront.terms', [
            'agent' => $agent,
        ]);
    }

    /**
     * Abort unless the offline payment simulator is enabled for this environment.
     *
     * The simulator marks payments as paid without money changing hands, so it must
     * never be reachable on a deployment wired to a real gateway.
     */
    protected function guardSimulatorEnabled(): void
    {
        abort_unless(DokuPaymentService::simulatorEnabled(), 404);
    }

    /**
     * Simulated DOKU Sandbox payment gateway screen for testing.
     */
    public function simulatePayment(Request $request): View
    {
        $this->guardSimulatorEnabled();

        $reservationId = (string) $request->query('reservation');
        $paymentId = (string) $request->query('payment');

        /** @var Reservation $reservation */
        $reservation = Reservation::query()->with(['agent', 'bookable'])->findOrFail($reservationId);
        /** @var Payment $payment */
        $payment = Payment::query()->where('reservation_id', $reservation->id)->findOrFail($paymentId);

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
        $this->guardSimulatorEnabled();

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

        return redirect()->route('storefront.reservation.receipt', $reservation);
    }

    /**
     * Abort when a reservation is requested from a storefront that does not own it.
     */
    protected function guardReservationBelongsToCurrentStorefront(Request $request, Reservation $reservation): void
    {
        $operator = $this->resolveCurrentOperator($request);

        if ($operator && $operator->id !== $reservation->operator_id) {
            abort(404);
        }
    }

    /**
     * Show guest reservation confirmation receipt.
     */
    public function showReceipt(Request $request, Reservation $reservation, DokuPaymentService $paymentService): View
    {
        $this->guardReservationBelongsToCurrentStorefront($request, $reservation);

        $reservation->load(['agent', 'bookable', 'latestPayment']);

        // Auto-check live status with DOKU if still pending
        if ($reservation->status === ReservationStatus::PaymentPending && $reservation->latestPayment) {
            $paymentService->syncPaymentStatus($reservation->latestPayment);
            $reservation->refresh();
            $reservation->load(['agent', 'bookable', 'latestPayment']);
        }

        return view('storefront.confirmation', [
            'reservation' => $reservation,
            'agent' => $reservation->agent,
        ]);
    }

    /**
     * Show the guest-facing HTML e-ticket.
     */
    public function showTicket(Request $request, Reservation $reservation): View|RedirectResponse
    {
        $this->guardReservationBelongsToCurrentStorefront($request, $reservation);

        $reservation->load(['agent', 'bookable', 'latestPayment']);

        $isPaid = $reservation->latestPayment?->isPaid()
            && in_array($reservation->status, [
                ReservationStatus::Confirmed,
                ReservationStatus::PendingConfirmation,
            ], true);

        if (! $isPaid) {
            return redirect()->route('storefront.reservation.receipt', $reservation);
        }

        return view('storefront.e-ticket', [
            'reservation' => $reservation,
            'agent' => $reservation->agent,
        ]);
    }

    /**
     * Resume or initiate payment for a pending reservation hold.
     */
    public function payReservation(Request $request, Reservation $reservation, DokuPaymentService $paymentService): RedirectResponse
    {
        $this->guardReservationBelongsToCurrentStorefront($request, $reservation);

        $reservation->load(['agent', 'bookable', 'latestPayment']);

        if ($reservation->status === ReservationStatus::Confirmed || $reservation->latestPayment?->isPaid()) {
            return redirect()->route('storefront.reservation.receipt', $reservation);
        }

        // If hold has expired, redirect with error
        if ($reservation->hold_expires_at && $reservation->hold_expires_at->isPast()) {
            return redirect()->route('home')->with('error', __('This booking hold has expired. Please create a new reservation.'));
        }

        $latestPayment = $reservation->latestPayment;
        $unitPrice = $reservation->bookable instanceof Bookable ? $reservation->bookable->getPrice() : 0.0;
        $termsSnapshot = $reservation->terms_snapshot ?? [];
        $totalAmount = isset($termsSnapshot['total_price'])
            ? (float) $termsSnapshot['total_price']
            : ($latestPayment ? (float) $latestPayment->amount : ($reservation->pax_count * $unitPrice));

        // Extending a hold re-claims inventory, so the date must still have room for this party
        if ($reservation->bookable instanceof Bookable) {
            try {
                app(CapacityService::class)->assertCanAccommodate(
                    $reservation->bookable,
                    $reservation->requested_date,
                    $reservation->pax_count,
                    $reservation->id,
                );
            } catch (CapacityUnavailableException $e) {
                return redirect()->route('home')->with('error', $e->getMessage());
            }
        }

        // When guest retries payment, ensure status is PaymentPending and hold is active
        if ($reservation->status !== ReservationStatus::Confirmed) {
            $reservation->update([
                'status' => ReservationStatus::PaymentPending,
                'hold_expires_at' => now()->addMinutes(30),
            ]);
        }

        $session = $paymentService->createPaymentSession($reservation, $totalAmount);

        return redirect($session['checkout_url']);
    }

    /**
     * Let a guest cancel from the public receipt while payment is unpaid or still inside the free-cancel window.
     */
    public function cancelReservation(Request $request, Reservation $reservation, GuestCancellationService $cancellations): RedirectResponse
    {
        $this->guardReservationBelongsToCurrentStorefront($request, $reservation);

        try {
            $cancellations->cancel($reservation);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('storefront.reservation.receipt', $reservation)
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('storefront.reservation.receipt', $reservation)
            ->with('success', __('Your booking has been cancelled.'));
    }

    /**
     * Let a guest recover their booking from the confirmation email code plus contact details.
     */
    public function findBooking(Request $request): View|\Symfony\Component\HttpFoundation\Response
    {
        $agent = $this->resolveCurrentOperator($request);

        if (! $agent) {
            abort(404);
        }

        if ($statusResponse = $this->checkOperatorStatus($agent)) {
            return $statusResponse;
        }

        return view('storefront.find-booking', [
            'agent' => $agent,
        ]);
    }

    /**
     * Resolve a guest booking lookup and send them to the receipt page.
     */
    public function lookupBooking(Request $request): RedirectResponse
    {
        $agent = $this->resolveCurrentOperator($request);

        if (! $agent) {
            abort(404);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:32'],
            'contact' => ['required', 'string', 'max:255'],
        ]);

        $code = strtoupper(trim($validated['code']));
        $contact = strtolower(trim($validated['contact']));
        $digits = preg_replace('/\D+/', '', $contact) ?? '';

        $reservation = Reservation::query()
            ->where('operator_id', $agent->id)
            ->where('code', $code)
            ->where(function ($query) use ($contact, $digits): void {
                $query->whereRaw('LOWER(guest_email) = ?', [$contact])
                    ->orWhereRaw('LOWER(guest_name) = ?', [$contact]);

                if ($digits !== '') {
                    $query->orWhere('guest_contact', 'like', '%'.$digits.'%');
                }
            })
            ->first();

        if (! $reservation) {
            return back()
                ->withInput()
                ->withErrors([
                    'code' => __('We could not find a booking with those details. Check the code from your email or WhatsApp message.'),
                ]);
        }

        return redirect()->route('storefront.reservation.receipt', $reservation);
    }

    /**
     * Generate dynamic robots.txt for current domain.
     */
    public function robots(Request $request): Response
    {
        $agent = $this->resolveCurrentAgent($request);
        $baseUrl = $request->getSchemeAndHttpHost();

        $content = "User-agent: *\n";

        if ($agent && ! $agent->isStorefrontPublic()) {
            $content .= "Disallow: /\n\n";
            $content .= "Sitemap: {$baseUrl}/sitemap.xml\n";

            return response($content, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $content .= "Allow: /\n";
        $content .= "Disallow: /admin/\n";
        $content .= "Disallow: /dashboard/\n";
        $content .= "Disallow: /checkout/\n";
        $content .= "Disallow: /api/\n";
        $content .= "Disallow: /livewire/\n\n";

        $hasAiDiscovery = $agent ? $agent->hasFeature('ai_discovery') : true;

        if ($hasAiDiscovery) {
            $content .= "# AI Discovery & LLM Crawlers Directives (Enabled)\n";
            $content .= "User-agent: GPTBot\nAllow: /\n";
            $content .= "User-agent: ChatGPT-User\nAllow: /\n";
            $content .= "User-agent: PerplexityBot\nAllow: /\n";
            $content .= "User-agent: ClaudeBot\nAllow: /\n";
            $content .= "User-agent: Claude-Web\nAllow: /\n";
            $content .= "User-agent: Google-Extended\nAllow: /\n";
            $content .= "User-agent: Applebot-Extended\nAllow: /\n";
            $content .= "User-agent: cohere-ai\nAllow: /\n";
            $content .= "User-agent: anthropic-ai\nAllow: /\n\n";
        } else {
            $content .= "# AI Discovery Crawlers Disallowed (Upgrade to Agency Plan to enable AI Search indexing)\n";
            $content .= "User-agent: GPTBot\nDisallow: /\n";
            $content .= "User-agent: ChatGPT-User\nDisallow: /\n";
            $content .= "User-agent: PerplexityBot\nDisallow: /\n";
            $content .= "User-agent: ClaudeBot\nDisallow: /\n";
            $content .= "User-agent: Claude-Web\nDisallow: /\n";
            $content .= "User-agent: Google-Extended\nDisallow: /\n";
            $content .= "User-agent: Applebot-Extended\nDisallow: /\n";
            $content .= "User-agent: cohere-ai\nDisallow: /\n";
            $content .= "User-agent: anthropic-ai\nDisallow: /\n\n";
        }

        $content .= "Sitemap: {$baseUrl}/sitemap.xml\n";
        if ($agent && $hasAiDiscovery) {
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

        if (! $agent) {
            $xml .= "  <url>\n    <loc>{$baseUrl}/legal</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.5</priority>\n  </url>\n";
            $xml .= "  <url>\n    <loc>{$baseUrl}/privacy</loc>\n    <changefreq>monthly</changefreq>\n    <priority>0.5</priority>\n  </url>\n";
        }

        if ($agent && $agent->isStorefrontPublic()) {
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

        if (! $agent->isStorefrontPublic()) {
            return response("# This booking page is not open yet.\n", 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (! $agent->hasFeature('ai_discovery')) {
            $content = "# AI Discovery Not Unlocked for {$agent->name}\n\n";
            $content .= "AI Search indexing and ChatGPT recommendation feeds (/llms.txt) are exclusive to the **Agency** subscription plan.\n";
            $content .= "Upgrade at {$baseUrl}/settings/plan to activate AI Search Engine discovery.\n";

            return response($content, 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
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

        if (! $agent->isStorefrontPublic()) {
            return response("# This booking page is not open yet.\n", 503, ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (! $agent->hasFeature('ai_discovery')) {
            $content = "# AI Discovery Not Unlocked for {$agent->name}\n\n";
            $content .= "AI Search indexing and ChatGPT recommendation feeds (/llms-full.txt) are exclusive to the **Agency** subscription plan.\n";
            $content .= "Upgrade at {$baseUrl}/settings/plan to activate AI Search Engine discovery.\n";

            return response($content, 403, ['Content-Type' => 'text/plain; charset=UTF-8']);
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
