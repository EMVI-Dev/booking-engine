<?php

namespace App\Services;

use App\Enums\ListingStatus;
use App\Models\Operator;
use Illuminate\Validation\ValidationException;

/**
 * Storefront FAQ. Ready on day one: answers are generated from data the operator already
 * has (payment, cancellation, confirmation, tickets, finding a booking, contact). The
 * operator may hide any generated answer and add their own questions; nothing has to be
 * maintained. Stored in settings.faq = {hidden: [keys], items: [{question, answer}]}.
 */
class StorefrontFaqService
{
    public const MAX_CUSTOM_ITEMS = 15;

    public const QUESTION_MAX = 150;

    public const ANSWER_MAX = 1000;

    /**
     * Everything the storefront shows, generated answers first, then the operator's own.
     *
     * @return list<array{key: string, question: string, answer: string, generated: bool}>
     */
    public function itemsFor(Operator $operator): array
    {
        $hidden = $this->hiddenKeys($operator);

        $generated = array_values(array_filter(
            $this->generatedFor($operator),
            fn (array $item): bool => ! in_array($item['key'], $hidden, true),
        ));

        return [...$generated, ...$this->customItems($operator)];
    }

    /**
     * Generated answers, including hidden ones (for the settings screen).
     *
     * @return list<array{key: string, question: string, answer: string, generated: bool}>
     */
    public function generatedFor(Operator $operator): array
    {
        $items = [];

        $items[] = $this->generated('payment', __('How do I pay?'), __('Pay online when you book: QRIS, bank transfer (virtual account) or card. Payment is handled securely by DOKU, a licensed Indonesian payment company.'));

        $items[] = $this->generated('confirmation', __('Is my booking confirmed straight away?'), $operator->isManualConfirmationEnabled()
            ? __('We check every booking personally and confirm it shortly after you pay. You will get a message as soon as it is confirmed.')
            : __('Yes. Your booking is confirmed as soon as your payment goes through.'));

        $items[] = $this->generated('ticket', __('Will I get a ticket?'), __('Yes. Your e-ticket is emailed after payment and is always available from your booking page. Show it on your phone; no need to print.'));

        if ($cancellation = $this->cancellationAnswer($operator)) {
            $items[] = $this->generated('cancellation', __('Can I cancel?'), $cancellation);
        }

        $items[] = $this->generated('find_booking', __('I lost my booking link. How do I find it?'), __('Open Find Booking on this site and enter your booking code with the email or phone number you booked with.'));

        $items[] = $this->generated('contact', __('How can I contact you?'), filled($operator->contact_whatsapp)
            ? __('Message us on WhatsApp using the button on this page. We usually reply quickly.')
            : __('Use the contact details on this page and we will get back to you.'));

        return $items;
    }

    /**
     * @return list<array{key: string, question: string, answer: string, generated: bool}>
     */
    public function customItems(Operator $operator): array
    {
        $items = [];

        foreach ((array) ($operator->settings['faq']['items'] ?? []) as $index => $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question !== '' && $answer !== '') {
                $items[] = ['key' => 'custom_'.$index, 'question' => $question, 'answer' => $answer, 'generated' => false];
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    public function hiddenKeys(Operator $operator): array
    {
        return array_values(array_filter((array) ($operator->settings['faq']['hidden'] ?? []), 'is_string'));
    }

    /**
     * Save the operator's FAQ choices.
     *
     * @param  list<string>  $hiddenKeys
     * @param  list<array{question?: string, answer?: string}>  $customItems
     *
     * @throws ValidationException
     */
    public function save(Operator $operator, array $hiddenKeys, array $customItems): void
    {
        $knownKeys = array_column($this->generatedFor($operator), 'key');
        $items = [];

        foreach ($customItems as $index => $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' && $answer === '') {
                continue;
            }

            if ($question === '' || $answer === '') {
                throw ValidationException::withMessages(["faqItems.{$index}" => __('Each question needs an answer.')]);
            }

            if (mb_strlen($question) > self::QUESTION_MAX || mb_strlen($answer) > self::ANSWER_MAX) {
                throw ValidationException::withMessages(["faqItems.{$index}" => __('Keep questions under :q and answers under :a characters.', ['q' => self::QUESTION_MAX, 'a' => self::ANSWER_MAX])]);
            }

            $items[] = ['question' => $question, 'answer' => $answer];
        }

        if (count($items) > self::MAX_CUSTOM_ITEMS) {
            throw ValidationException::withMessages(['faqItems' => __('You can add up to :max questions.', ['max' => self::MAX_CUSTOM_ITEMS])]);
        }

        $settings = $operator->settings ?? [];
        $settings['faq'] = [
            'hidden' => array_values(array_intersect($hiddenKeys, $knownKeys)),
            'items' => $items,
        ];
        $operator->update(['settings' => $settings]);
    }

    /**
     * One clear sentence when every published trip shares the same free-cancellation window;
     * otherwise point guests to the trip page, where each policy is shown.
     */
    private function cancellationAnswer(Operator $operator): ?string
    {
        $hours = collect([
            ...$operator->packages()->where('status', ListingStatus::Published)->pluck('free_cancellation_hours')->all(),
            ...$operator->products()->where('status', ListingStatus::Published)->pluck('free_cancellation_hours')->all(),
        ])->map(fn ($value): int => (int) $value)->unique()->values();

        if ($hours->isEmpty()) {
            return null;
        }

        if ($hours->count() > 1) {
            return __('Each trip shows its own cancellation policy on its page, before you pay.');
        }

        $window = (int) $hours->first();

        if ($window <= 0) {
            return __('Bookings cannot be cancelled for a refund. Message us if your plans change and we will try to help.');
        }

        return $window % 24 === 0
            ? trans_choice('Free cancellation up to :count day before the trip. The refund goes back to your original payment method.|Free cancellation up to :count days before the trip. The refund goes back to your original payment method.', intdiv($window, 24))
            : __('Free cancellation up to :hours hours before the trip. The refund goes back to your original payment method.', ['hours' => $window]);
    }

    /**
     * @return array{key: string, question: string, answer: string, generated: bool}
     */
    private function generated(string $key, string $question, string $answer): array
    {
        return ['key' => $key, 'question' => $question, 'answer' => $answer, 'generated' => true];
    }
}
