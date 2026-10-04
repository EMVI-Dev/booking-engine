<?php

use App\Enums\DomainStatus;
use App\Enums\DomainType;
use App\Enums\ListingStatus;
use App\Enums\OperatorStatus;
use App\Enums\OperatorUserRole;
use App\Mail\OperatorEnquiryMail;
use App\Models\Enquiry;
use App\Models\Operator;
use App\Models\OperatorDomain;
use App\Models\OperatorGalleryPhoto;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\MediaStore;
use App\Services\StorefrontFaqService;
use App\Services\StorefrontGalleryService;
use App\Services\StorefrontPagesService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    Cache::flush();
    Plan::seedDefaultPlans();

    $this->owner = User::factory()->create();
    $this->operator = Operator::factory()->create([
        'slug' => 'reef-site',
        'status' => OperatorStatus::Approved,
        'plan_id' => Plan::where('slug', 'growth')->value('id'),
        'contact_whatsapp' => '+6281234567890',
        'booking_notification_email' => 'bookings@reef.test',
    ]);
    $this->operator->users()->attach($this->owner->id, ['role' => OperatorUserRole::Owner]);
});

function onPlan(Operator $operator, string $slug): void
{
    $operator->update(['plan_id' => Plan::where('slug', $slug)->value('id')]);
    $operator->unsetRelation('plan');
}

function openShop(Operator $operator): void
{
    OperatorDomain::factory()->create([
        'operator_id' => $operator->id,
        'domain' => $operator->slug.'.booking.test',
        'type' => DomainType::Subdomain,
        'status' => DomainStatus::Active,
    ]);
}

// ── Plans ────────────────────────────────────────────────────────────────────

test('gallery sizes fill a 3-column grid and the contact form is on paid plans only', function () {
    expect(Plan::where('slug', 'starter')->value('gallery_photo_limit'))->toBe(9)
        ->and(Plan::where('slug', 'growth')->value('gallery_photo_limit'))->toBe(18)
        ->and(Plan::where('slug', 'agency')->value('gallery_photo_limit'))->toBe(36)
        ->and(Plan::where('slug', 'starter')->first()->hasFeature('contact_form'))->toBeFalse()
        ->and(Plan::where('slug', 'growth')->first()->hasFeature('contact_form'))->toBeTrue()
        ->and(Plan::where('slug', 'agency')->first()->hasFeature('contact_form'))->toBeTrue();
});

// ── Gallery ──────────────────────────────────────────────────────────────────

test('gallery photos are stored as webp in the operator folder and capped by the plan', function () {
    Storage::fake(MediaStore::diskName());
    onPlan($this->operator, 'starter');
    $gallery = app(StorefrontGalleryService::class);

    foreach (range(1, 9) as $i) {
        $gallery->add($this->operator, UploadedFile::fake()->image("p{$i}.jpg", 2400, 1600));
    }

    $photo = $this->operator->galleryPhotos()->first();
    expect($photo->path)->toStartWith('operators/'.$this->operator->id.'/gallery/')->toEndWith('.webp');
    Storage::disk(MediaStore::diskName())->assertExists($photo->path);

    expect(fn () => $gallery->add($this->operator, UploadedFile::fake()->image('ten.jpg')))
        ->toThrow(ValidationException::class);
    expect($this->operator->galleryPhotos()->count())->toBe(9);
});

test('removing a gallery photo deletes its file; reorder only touches own photos', function () {
    Storage::fake(MediaStore::diskName());
    $gallery = app(StorefrontGalleryService::class);
    $first = $gallery->add($this->operator, UploadedFile::fake()->image('a.jpg'));
    $second = $gallery->add($this->operator, UploadedFile::fake()->image('b.jpg'));
    $foreign = OperatorGalleryPhoto::factory()->create(['sort_order' => 50]);

    $gallery->reorder($this->operator, [$second->id, $foreign->id, $first->id]);
    expect($this->operator->galleryPhotos()->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and($foreign->fresh()->sort_order)->toBe(50);

    $gallery->remove($this->operator, $first->id);
    Storage::disk(MediaStore::diskName())->assertMissing($first->path);

    expect(fn () => $gallery->remove($this->operator, $foreign->id))
        ->toThrow(ModelNotFoundException::class);
});

test('after a downgrade extra photos are kept but only the plan limit is shown', function () {
    OperatorGalleryPhoto::factory()->count(12)->sequence(fn ($s) => ['sort_order' => $s->index + 1])
        ->create(['operator_id' => $this->operator->id]);
    onPlan($this->operator, 'starter');

    expect(app(StorefrontGalleryService::class)->visiblePhotos($this->operator))->toHaveCount(9)
        ->and($this->operator->galleryPhotos()->count())->toBe(12);
});

test('the gallery settings page uploads and shows the count', function () {
    Storage::fake(MediaStore::diskName());
    $this->actingAs($this->owner);

    Livewire::test('pages::settings.website')
        ->assertSee('0 of 18 photos')
        ->set('uploads', [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')])
        ->assertHasNoErrors()
        ->assertSee('2 of 18 photos');
});

// ── FAQ ──────────────────────────────────────────────────────────────────────

test('the faq is ready on day one from existing settings', function () {
    Product::factory()->create(['operator_id' => $this->operator->id, 'status' => ListingStatus::Published, 'free_cancellation_hours' => 48]);

    $items = collect(app(StorefrontFaqService::class)->itemsFor($this->operator));

    expect($items->pluck('key')->all())->toContain('payment', 'confirmation', 'ticket', 'cancellation', 'find_booking', 'contact')
        ->and($items->firstWhere('key', 'cancellation')['answer'])->toContain('2 days');
});

test('operators can hide generated answers and add their own questions', function () {
    $faq = app(StorefrontFaqService::class);
    $faq->save($this->operator, ['ticket', 'not-a-key'], [['question' => 'Do you pick up from Ubud?', 'answer' => 'Yes, free.'], ['question' => '', 'answer' => '']]);

    $items = collect($faq->itemsFor($this->operator->fresh()));

    expect($items->pluck('key'))->not->toContain('ticket')
        ->and($items->last()['question'])->toBe('Do you pick up from Ubud?')
        ->and($this->operator->fresh()->settings['faq']['hidden'])->toBe(['ticket']);

    expect(fn () => $faq->save($this->operator, [], [['question' => 'Only a question', 'answer' => '']]))
        ->toThrow(ValidationException::class);
});

test('the storefront shows dedicated pages for gallery, faq with search markup, and contact', function () {
    openShop($this->operator);
    OperatorGalleryPhoto::factory()->create(['operator_id' => $this->operator->id, 'caption' => 'Sunset at the reef']);
    app(EnquiryService::class)->saveSettings($this->operator, true, false, null);

    // Homepage links to them
    $this->get('http://reef-site.booking.test/')
        ->assertOk()
        ->assertSee('/gallery')
        ->assertSee('/faq')
        ->assertSee('/contact');

    // Dedicated Gallery Page
    $this->get('http://reef-site.booking.test/gallery')
        ->assertOk()
        ->assertSee('Photo Gallery')
        ->assertSee('Sunset at the reef');

    // Dedicated FAQ Page
    $this->get('http://reef-site.booking.test/faq')
        ->assertOk()
        ->assertSee('Frequently Asked Questions')
        ->assertSee('How do I pay?')
        ->assertSee('"@type":"FAQPage"', false);

    // Dedicated Contact Page
    $this->get('http://reef-site.booking.test/contact')
        ->assertOk()
        ->assertSee('Get in Touch')
        ->assertSee('Send Message');
});

/**
 * @return list<array<string, mixed>>
 */
function jsonLdNodes(string $html): array
{
    preg_match('#<script type="application/ld\+json">\s*(.+?)\s*</script>#s', $html, $match);

    return json_decode($match[1] ?? '{}', true)['@graph'] ?? [];
}

function jsonLdNode(string $html, string $type): ?array
{
    return collect(jsonLdNodes($html))->firstWhere('@type', $type);
}

test('faq search markup lives on the faq page only, linked to the site and shop', function () {
    openShop($this->operator);

    $home = $this->get('http://reef-site.booking.test/')->assertOk()->getContent();
    expect(jsonLdNode($home, 'FAQPage'))->toBeNull();

    $faqPage = jsonLdNode($this->get('http://reef-site.booking.test/faq')->assertOk()->getContent(), 'FAQPage');

    expect($faqPage['@id'])->toBe('http://reef-site.booking.test/faq#webpage')
        ->and($faqPage['url'])->toBe('http://reef-site.booking.test/faq')
        ->and($faqPage['isPartOf']['@id'])->toBe('http://reef-site.booking.test#website')
        ->and(collect($faqPage['mainEntity'])->pluck('name'))->toContain('How do I pay?');
});

test('the gallery page lists every visible photo as an image object', function () {
    openShop($this->operator);
    OperatorGalleryPhoto::factory()->create(['operator_id' => $this->operator->id, 'caption' => 'Sunset at the reef', 'sort_order' => 1]);
    OperatorGalleryPhoto::factory()->create(['operator_id' => $this->operator->id, 'caption' => null, 'sort_order' => 2]);

    $html = $this->get('http://reef-site.booking.test/gallery')->assertOk()->getContent();
    $gallery = jsonLdNode($html, 'ImageGallery');

    expect($gallery['@id'])->toBe('http://reef-site.booking.test/gallery#webpage')
        ->and($gallery['image'])->toHaveCount(2)
        ->and($gallery['image'][0]['@type'])->toBe('ImageObject')
        ->and($gallery['image'][0]['contentUrl'])->toStartWith('http')
        ->and($gallery['image'][0]['caption'])->toBe('Sunset at the reef')
        ->and($gallery['primaryImageOfPage']['contentUrl'])->toBe($gallery['image'][0]['contentUrl'])
        ->and(jsonLdNode($html, 'BreadcrumbList'))->not->toBeNull()
        ->and($html)->toContain('<meta property="og:type" content="website" />');
});

test('the contact page is a contact page about the shop and never shows the private booking email', function () {
    openShop($this->operator);

    $html = $this->get('http://reef-site.booking.test/contact')->assertOk()->getContent();

    expect(jsonLdNode($html, 'ContactPage')['mainEntity']['@id'])->toBe('http://reef-site.booking.test#agency')
        ->and(jsonLdNode($html, 'TravelAgency'))->not->toHaveKey('email')
        ->and($html)->not->toContain('bookings@reef.test');
});

test('gallery and faq pages 404 and are not linked or listed while they have nothing to show', function () {
    openShop($this->operator);
    app(StorefrontFaqService::class)->save(
        $this->operator,
        array_column(app(StorefrontFaqService::class)->generatedFor($this->operator), 'key'),
        [],
    );

    $this->get('http://reef-site.booking.test/gallery')->assertNotFound();
    $this->get('http://reef-site.booking.test/faq')->assertNotFound();

    $home = $this->get('http://reef-site.booking.test/')->assertOk()->getContent();
    expect($home)->not->toContain('href="http://reef-site.booking.test/gallery"')
        ->not->toContain('href="http://reef-site.booking.test/faq"')
        ->toContain('href="http://reef-site.booking.test/contact"');

    $sitemap = $this->get('http://reef-site.booking.test/sitemap.xml')->assertOk()->getContent();
    expect($sitemap)->not->toContain('/gallery</loc>')->not->toContain('/faq</loc>')->toContain('/contact</loc>');
});

test('the contact page needs the contact form or a whatsapp number', function () {
    $pages = app(StorefrontPagesService::class);
    $withoutWhatsApp = Operator::factory()->create(['plan_id' => $this->operator->plan_id, 'contact_whatsapp' => null]);

    expect($pages->has($this->operator->fresh(), StorefrontPagesService::CONTACT))->toBeTrue()
        ->and($pages->has($withoutWhatsApp, StorefrontPagesService::CONTACT))->toBeFalse();

    app(EnquiryService::class)->saveSettings($withoutWhatsApp, true, false, null);

    expect($pages->has($withoutWhatsApp->fresh(), StorefrontPagesService::CONTACT))->toBeTrue();
});

test('the contact page works with whatsapp only', function () {
    openShop($this->operator);

    $this->get('http://reef-site.booking.test/contact')->assertOk()->assertDontSee('Send Message');
});

test('the platform root has no gallery, faq or contact page', function () {
    $this->get('/gallery')->assertNotFound();
    $this->get('/faq')->assertNotFound();
    $this->get('/contact')->assertNotFound();
});

test('sitemap and llms files include the pages a shop has', function () {
    openShop($this->operator);
    onPlan($this->operator, 'agency');
    OperatorGalleryPhoto::factory()->create(['operator_id' => $this->operator->id]);

    $this->get('http://reef-site.booking.test/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>http://reef-site.booking.test/gallery</loc>', false)
        ->assertSee('<loc>http://reef-site.booking.test/faq</loc>', false)
        ->assertSee('<loc>http://reef-site.booking.test/contact</loc>', false);

    $this->get('http://reef-site.booking.test/llms.txt')
        ->assertOk()
        ->assertSee('(http://reef-site.booking.test/faq)', false)
        ->assertSee('(http://reef-site.booking.test/gallery)', false);

    $this->get('http://reef-site.booking.test/llms-full.txt')
        ->assertOk()
        ->assertSee('## Frequently Asked Questions', false)
        ->assertSee('How do I pay?');
});

// ── Contact form & enquiries ─────────────────────────────────────────────────

test('the contact form is off by default and never on the free plan', function () {
    $service = app(EnquiryService::class);
    expect($service->isOpen($this->operator))->toBeFalse();

    $service->saveSettings($this->operator, true, false, null);
    expect($service->isOpen($this->operator->fresh()))->toBeTrue();

    onPlan($this->operator, 'starter');
    expect($service->isOpen($this->operator->fresh()))->toBeFalse();
});

test('a guest enquiry is saved for the desk, emailed only when asked, and rate limited', function () {
    Mail::fake();
    $service = app(EnquiryService::class);
    $service->saveSettings($this->operator, true, false, null);
    $data = ['type' => Enquiry::TYPE_PRIVATE_GROUP, 'name' => 'Ayu', 'whatsapp' => '+62 812 3456 789', 'preferred_date' => now()->addDays(10)->toDateString(), 'group_size' => 12, 'message' => 'Boat for 12 people please'];

    $enquiry = $service->submit($this->operator->fresh(), $data, 'visitor-1');

    expect($enquiry->isPrivateGroup())->toBeTrue()->and($enquiry->group_size)->toBe(12);
    Mail::assertNothingQueued();

    $service->saveSettings($this->operator, true, true, null);
    $service->submit($this->operator->fresh(), $data, 'visitor-1');
    Mail::assertQueued(OperatorEnquiryMail::class, fn ($mail) => $mail->hasTo('bookings@reef.test'));

    $service->submit($this->operator->fresh(), $data, 'visitor-1');
    expect(fn () => $service->submit($this->operator->fresh(), $data, 'visitor-1'))->toThrow(ValidationException::class);
});

test('the storefront form stores a real enquiry and silently drops bots', function () {
    app(EnquiryService::class)->saveSettings($this->operator, true, false, null);
    $this->travelTo(now());

    $form = Livewire::test('storefront.contact-form', ['operator' => $this->operator->fresh()]);
    $this->travel(5)->seconds();
    $form->set('name', 'Ayu')->set('whatsapp', '081234567890')->set('message', 'Do you have space on Friday?')
        ->call('send')->assertHasNoErrors()->assertSet('sent', true);

    $bot = Livewire::test('storefront.contact-form', ['operator' => $this->operator->fresh()]);
    $this->travel(5)->seconds();
    $bot->set('name', 'Bot')->set('whatsapp', '081234567890')->set('message', 'Buy cheap stuff')->set('website', 'http://spam.test')
        ->call('send')->assertSet('sent', true);

    $tooFast = Livewire::test('storefront.contact-form', ['operator' => $this->operator->fresh()]);
    $tooFast->set('name', 'Fast')->set('whatsapp', '081234567890')->set('message', 'Instant submit')->call('send');

    expect(Enquiry::query()->pluck('name')->all())->toBe(['Ayu']);
});

test('the desk lists enquiries with a whatsapp reply link, and only for the right role', function () {
    $enquiry = Enquiry::factory()->privateGroup()->create(['operator_id' => $this->operator->id, 'name' => 'Ayu', 'whatsapp' => '081234567890']);
    $this->actingAs($this->owner);

    Livewire::test('pages::enquiries.index')
        ->assertSee('Ayu')
        ->assertSee('https://wa.me/6281234567890', false)
        ->call('markRead', $enquiry->id);

    expect($enquiry->fresh()->read_at)->not->toBeNull();

    $finance = User::factory()->create();
    $this->operator->users()->attach($finance->id, ['role' => OperatorUserRole::Finance]);
    $this->actingAs($finance);
    Livewire::test('pages::enquiries.index')->assertForbidden();
});

test('another operator\'s enquiry cannot be read or deleted', function () {
    $foreign = Enquiry::factory()->create();
    $this->actingAs($this->owner);

    Livewire::test('pages::enquiries.index')->call('delete', $foreign->id);

    expect($foreign->fresh())->not->toBeNull();
});

// ── Cookie consent ───────────────────────────────────────────────────────────

test('tracking scripts stay inert until the guest accepts cookies', function () {
    onPlan($this->operator, 'growth');
    $this->operator->update(['settings' => array_merge($this->operator->settings ?? [], ['tracking' => ['google_analytics_id' => 'G-ABC123XYZ']])]);
    openShop($this->operator);

    $html = $this->get('http://reef-site.booking.test/')->assertOk()->getContent();

    expect($html)->toContain('type="text/plain" data-consent="analytics" data-src="https://www.googletagmanager.com/gtag/js?id=G-ABC123XYZ"')
        ->not->toContain('<script async src="https://www.googletagmanager.com/gtag/js')
        ->toContain('te_consent')
        ->toContain('Cookie settings');
});

test('no cookie notice on a shop without trackers', function () {
    openShop($this->operator);

    $this->get('http://reef-site.booking.test/')->assertOk()->assertDontSee('te_consent')->assertDontSee('Cookie settings');
});

test('website settings page loads without error for an admin without an active operator', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    Livewire::test('pages::settings.website')
        ->assertOk()
        ->assertSee('Gallery')
        ->assertSee('FAQ')
        ->assertSee('Contact');
});

test('gallery faq and contact have separate tabs in settings', function () {
    $this->actingAs($this->owner);

    // Default tab is gallery
    Livewire::test('pages::settings.website')
        ->assertSee('Photo gallery')
        ->assertSee('Photos are resized to 1600px')
        ->assertDontSee('Automated Answers (from your shop settings)')
        ->assertDontSee('Show the contact form on my website');

    // Switch to FAQ tab
    Livewire::test('pages::settings.website', ['tab' => 'faq'])
        ->assertSee('FAQ')
        ->assertSee('Automated Answers (from your shop settings)')
        ->assertSee('Your Own Questions')
        ->assertDontSee('Photos are resized to 1600px')
        ->assertDontSee('Show the contact form on my website');

    // Switch to Contact tab
    Livewire::test('pages::settings.website', ['tab' => 'contact'])
        ->assertSee('Contact & private group form')
        ->assertSee('Show the contact form on my website')
        ->assertDontSee('Photos are resized to 1600px')
        ->assertDontSee('Automated Answers (from your shop settings)');
});

test('settings routes redirect to the corresponding website tab', function () {
    $this->actingAs($this->owner);

    $this->get('/settings/gallery')->assertRedirect('/settings/website?tab=gallery');
    $this->get('/settings/faq')->assertRedirect('/settings/website?tab=faq');
    $this->get('/settings/contact')->assertRedirect('/settings/website?tab=contact');
});

test('a shop name with quotes cannot break or inject into the gallery lightbox', function () {
    $this->operator->update(['name' => "Wayan's Tours'); alert(1); ('"]);
    openShop($this->operator);
    OperatorGalleryPhoto::factory()->create(['operator_id' => $this->operator->id, 'caption' => null]);

    $html = $this->get('http://reef-site.booking.test/gallery')->assertOk()->getContent();

    expect($html)->toContain(Js::from("Wayan's Tours'); alert(1); ('")->toHtml())
        ->not->toContain("|| 'Wayan&#039;s");
});
