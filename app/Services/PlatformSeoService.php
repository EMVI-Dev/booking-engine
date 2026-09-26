<?php

namespace App\Services;

class PlatformSeoService
{
    /**
     * @return array{url: string, alt: string, card: string, width: int, height: int}
     */
    public function shareImage(): array
    {
        $name = $this->platformName();

        return [
            'url' => url('/images/hero-cover.jpg'),
            'alt' => $name,
            'card' => 'summary_large_image',
            'width' => 1200,
            'height' => 630,
        ];
    }

    public function platformName(): string
    {
        return (string) config('app.name', 'TravelEngine');
    }

    public function homeTitle(): string
    {
        return $this->platformName().' - Website + Booking Engine + Payment in one platform';
    }

    public function homeDescription(): string
    {
        return 'Website + Booking Engine + Payment in one platform. Add your trips, share one link, and let guests pick a date and pay. QRIS or bank transfer, WhatsApp tickets, and you keep 100% of the listed price.';
    }

    /**
     * @return array<string, mixed>
     */
    public function homeGraph(): array
    {
        $share = $this->shareImage();

        return $this->graph([
            $this->organization($share['url']),
            $this->webSite(),
            $this->webPage($share['url']),
            $this->softwareApplication($share['url']),
            $this->faqPage(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function legalGraph(string $title, string $url): array
    {
        $share = $this->shareImage();

        return $this->graph([
            $this->breadcrumbs([
                ['name' => __('Home'), 'url' => url('/')],
                ['name' => $title, 'url' => $url],
            ]),
            $this->organization($share['url']),
            [
                '@type' => 'WebPage',
                '@id' => $url.'#webpage',
                'name' => $title,
                'url' => $url,
                'isPartOf' => ['@id' => url('/').'#website'],
                'publisher' => ['@id' => url('/').'#organization'],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function organization(string $imageUrl): array
    {
        return [
            '@type' => 'Organization',
            '@id' => url('/').'#organization',
            'name' => $this->platformName(),
            'url' => url('/'),
            'logo' => url('/favicon.png'),
            'image' => $imageUrl,
            'email' => config('mail.from.address', 'no-reply@travelengine.id'),
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'Indonesia',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function webSite(): array
    {
        return [
            '@type' => 'WebSite',
            '@id' => url('/').'#website',
            'url' => url('/'),
            'name' => $this->platformName(),
            'description' => $this->homeDescription(),
            'publisher' => [
                '@id' => url('/').'#organization',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function webPage(string $imageUrl): array
    {
        return [
            '@type' => 'WebPage',
            '@id' => url('/').'#webpage',
            'url' => url('/'),
            'name' => $this->homeTitle(),
            'description' => $this->homeDescription(),
            'isPartOf' => ['@id' => url('/').'#website'],
            'about' => ['@id' => url('/').'#app'],
            'primaryImageOfPage' => [
                '@type' => 'ImageObject',
                'url' => $imageUrl,
            ],
            'inLanguage' => ['id-ID', 'en-US'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function softwareApplication(string $imageUrl): array
    {
        return [
            '@type' => 'SoftwareApplication',
            '@id' => url('/').'#app',
            'name' => $this->platformName(),
            'url' => url('/'),
            'image' => $imageUrl,
            'description' => $this->homeDescription(),
            'applicationCategory' => 'BusinessApplication',
            'applicationSubCategory' => 'Tour Operator Reservation & Payment Platform',
            'operatingSystem' => 'Web',
            'slogan' => 'Guests book themselves. You keep the listed price.',
            'featureList' => [
                'Website + Booking Engine + Payment in one platform',
                'Your own tour shop website',
                'Direct booking and reservation engine',
                'QRIS and Indonesian bank transfers',
                'Tickets on WhatsApp',
                'Daily pickup lists for drivers and guides',
                'You keep 100% of the listed price',
            ],
            'offers' => [
                [
                    '@type' => 'Offer',
                    'name' => 'Starter Plan',
                    'price' => '0',
                    'priceCurrency' => 'IDR',
                    'description' => 'Free Starter plan forever. Tour website and 24/7 direct booking.',
                ],
                [
                    '@type' => 'Offer',
                    'name' => 'Growth Plan',
                    'price' => '299000',
                    'priceCurrency' => 'IDR',
                    'description' => 'WhatsApp tickets, daily guest lists, and calendar integration.',
                ],
                [
                    '@type' => 'Offer',
                    'name' => 'Agency Plan',
                    'price' => '799000',
                    'priceCurrency' => 'IDR',
                    'description' => 'Custom domain (yourbrand.com), remove branding, and AI discovery.',
                ],
            ],
            'publisher' => [
                '@id' => url('/').'#organization',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function faqPage(): array
    {
        $faqs = [
            [
                'q' => 'What does “Website + Booking Engine + Payment in one platform” mean?',
                'a' => 'It means you do not need separate subscriptions for a website builder, an external booking plugin, and a payment gateway. TravelEngine gives you all three in one place: a mobile-ready tour website, live booking calendar with holds, and automated payments (QRIS, virtual accounts, cards) sent directly to your Indonesian bank account.',
            ],
            [
                'q' => 'How do I receive payouts from my tour bookings?',
                'a' => 'When a guest pays with QRIS, a bank transfer, or a card, the money sits in your TravelEngine wallet. After the bank has settled it, we send it to your Indonesian account (BCA, Mandiri, BRI, BNI, and more).',
            ],
            [
                'q' => 'Do I need a designer or a website person?',
                'a' => 'No. Add photos, prices, and your WhatsApp number. Share the link. That is the whole setup — usually under five minutes.',
            ],
            [
                'q' => 'How does “you keep 100%” work?',
                'a' => 'Your listed price is yours. We take 0% from the ticket — travel websites often take 15% to 30% from you instead. A 5% platform fee is added at checkout, the same idea as other booking apps, so you still receive 100% of the price you listed.',
            ],
            [
                'q' => 'What is the platform fee?',
                'a' => 'It is a small 5% fee added at checkout so you can keep the full ticket price while the shop stays simple to run. Guests see it on the payment screen before they confirm. It does not come out of your payout, and nothing is added later.',
            ],
            [
                'q' => 'Can guests open mybrand.com instead of a long link?',
                'a' => 'Yes, on the Agency plan. Guests can type yourbrand.com. The padlock in the browser is included — you do not set that up yourself.',
            ],
            [
                'q' => 'Can I change plans or cancel anytime?',
                'a' => 'Yes. There are no lock-in contracts or long-term commitments. Start on the free plan, upgrade when your business grows, or cancel anytime with 1 click.',
            ],
            [
                'q' => 'Can my tour guides and staff have their own accounts?',
                'a' => 'Starter is you and one helper. Growth and above allow as many people as you need — office staff, drivers, and guides.',
            ],
        ];

        return [
            '@type' => 'FAQPage',
            '@id' => url('/').'#faq',
            'isPartOf' => ['@id' => url('/').'#webpage'],
            'mainEntity' => array_map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['q'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['a'],
                ],
            ], $faqs),
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $crumbs
     * @return array<string, mixed>
     */
    private function breadcrumbs(array $crumbs): array
    {
        return [
            '@type' => 'BreadcrumbList',
            'itemListElement' => collect($crumbs)->values()->map(fn (array $crumb, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $crumb['name'],
                'item' => $crumb['url'],
            ])->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>|null>  $nodes
     * @return array<string, mixed>
     */
    private function graph(array $nodes): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => array_values(array_map(
                fn (array $node): array => $this->withoutEmpty($node),
                array_values(array_filter($nodes)),
            )),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutEmpty(array $data): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            if (is_array($value)) {
                $value = $this->withoutEmpty($value);

                if ($value === []) {
                    continue;
                }
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
