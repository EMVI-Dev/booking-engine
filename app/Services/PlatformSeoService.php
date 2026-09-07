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
        return $this->platformName().' - Guests book themselves. You keep the listed price.';
    }

    public function homeDescription(): string
    {
        return 'Add your trips, share one link, and let guests pick a date and pay. QRIS or bank transfer, WhatsApp tickets, and you keep 100% of the listed price.';
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
            $this->softwareApplication($share['url']),
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
            'email' => config('mail.from.address', 'no-reply@travelengine.online'),
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
            'operatingSystem' => 'Web',
            'slogan' => 'Guests book themselves. You keep the listed price.',
            'featureList' => [
                'Your own tour shop',
                'QRIS and bank transfers',
                'Tickets on WhatsApp',
                'You keep 100% of the listed price',
            ],
            'offers' => [
                '@type' => 'Offer',
                'price' => '0',
                'priceCurrency' => 'IDR',
            ],
            'publisher' => [
                '@id' => url('/').'#organization',
            ],
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
