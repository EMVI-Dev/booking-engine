<?php

namespace App\Services;

use App\Models\Operator;

/**
 * Which optional storefront pages a shop has: Gallery, FAQ and Contact.
 *
 * One answer for every place that needs it (navbar, footer, home links, the page
 * routes, sitemap, llms.txt), so a page is never linked or indexed while empty.
 * Answers are remembered per Operator instance (each request loads its own), so the
 * queries run once per page render and never go stale across requests.
 *
 * - Gallery: at least one visible photo (within the plan limit).
 * - FAQ: at least one answer left after the operator's hidden choices.
 * - Contact: the contact form is open, or the shop has a WhatsApp number.
 */
class StorefrontPagesService
{
    public const GALLERY = 'gallery';

    public const FAQ = 'faq';

    public const CONTACT = 'contact';

    /** @var \WeakMap<Operator, array{gallery: bool, faq: bool, contact: bool}> */
    private \WeakMap $cache;

    public function __construct(
        private StorefrontGalleryService $gallery,
        private StorefrontFaqService $faq,
        private EnquiryService $enquiries,
    ) {
        $this->cache = new \WeakMap;
    }

    /**
     * @return array{gallery: bool, faq: bool, contact: bool}
     */
    public function pagesFor(Operator $operator): array
    {
        return $this->cache[$operator] ??= [
            self::GALLERY => $this->gallery->visiblePhotos($operator)->isNotEmpty(),
            self::FAQ => $this->faq->itemsFor($operator) !== [],
            self::CONTACT => $this->enquiries->isOpen($operator) || filled($operator->contact_whatsapp),
        ];
    }

    public function has(Operator $operator, string $page): bool
    {
        return $this->pagesFor($operator)[$page] ?? false;
    }

    public function hasAny(Operator $operator): bool
    {
        return in_array(true, $this->pagesFor($operator), true);
    }
}
