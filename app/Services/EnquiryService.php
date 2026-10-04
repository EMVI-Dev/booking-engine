<?php

namespace App\Services;

use App\Mail\OperatorEnquiryMail;
use App\Models\Enquiry;
use App\Models\Operator;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Storefront contact / private-group enquiries (paid plans, opt-in per shop).
 *
 * Every enquiry is kept in the desk (so nothing is lost if the operator never reads email)
 * and emailed only when the operator turned email on. Spam is held off with free measures:
 * a honeypot and a minimum fill time in the form, plus a per-visitor rate limit here.
 * Settings live in settings.contact_form = {enabled, email_notifications, notify_email}.
 */
class EnquiryService
{
    public const PER_VISITOR_PER_HOUR = 3;

    public function __construct(
        private WhatsAppDispatchService $whatsApp,
    ) {}

    /**
     * Whether the storefront should show the form: paid plan and switched on by the operator.
     */
    public function isOpen(Operator $operator): bool
    {
        return $operator->hasFeature('contact_form') && (bool) ($operator->settings['contact_form']['enabled'] ?? false);
    }

    /**
     * @return array{enabled: bool, email_notifications: bool, notify_email: string|null}
     */
    public function settingsFor(Operator $operator): array
    {
        $saved = (array) ($operator->settings['contact_form'] ?? []);

        return [
            'enabled' => (bool) ($saved['enabled'] ?? false),
            'email_notifications' => (bool) ($saved['email_notifications'] ?? false),
            'notify_email' => filled($saved['notify_email'] ?? null) ? (string) $saved['notify_email'] : null,
        ];
    }

    public function saveSettings(Operator $operator, bool $enabled, bool $emailNotifications, ?string $notifyEmail): void
    {
        $settings = $operator->settings ?? [];
        $settings['contact_form'] = [
            'enabled' => $enabled,
            'email_notifications' => $emailNotifications,
            'notify_email' => filled($notifyEmail) ? strtolower(trim((string) $notifyEmail)) : null,
        ];
        $operator->update(['settings' => $settings]);
    }

    /**
     * Where enquiry emails go: the chosen address, else the booking notification email.
     */
    public function notificationAddress(Operator $operator): ?string
    {
        $settings = $this->settingsFor($operator);

        return $settings['notify_email'] ?? (filled($operator->booking_notification_email) ? (string) $operator->booking_notification_email : null);
    }

    /**
     * Store a guest enquiry and email the operator when they asked for it.
     *
     * @param  array{type: string, name: string, whatsapp: string, email?: ?string, preferred_date?: ?string, group_size?: ?int, message: string}  $data  already validated
     *
     * @throws ValidationException when the form is closed or the visitor sent too many
     */
    public function submit(Operator $operator, array $data, string $visitorKey): Enquiry
    {
        if (! $this->isOpen($operator)) {
            throw ValidationException::withMessages(['message' => __('This form is not available right now. Please message us on WhatsApp.')]);
        }

        $limiterKey = 'enquiry:'.$operator->id.':'.$visitorKey;
        if (RateLimiter::tooManyAttempts($limiterKey, self::PER_VISITOR_PER_HOUR)) {
            throw ValidationException::withMessages(['message' => __('You have sent a few messages already. Please wait a while, or message us on WhatsApp.')]);
        }
        RateLimiter::hit($limiterKey, 3600);

        $isGroup = $data['type'] === Enquiry::TYPE_PRIVATE_GROUP;

        /** @var Enquiry $enquiry */
        $enquiry = $operator->enquiries()->create([
            'type' => $isGroup ? Enquiry::TYPE_PRIVATE_GROUP : Enquiry::TYPE_GENERAL,
            'name' => trim($data['name']),
            'whatsapp' => trim($data['whatsapp']),
            'email' => filled($data['email'] ?? null) ? strtolower(trim((string) $data['email'])) : null,
            'preferred_date' => $isGroup && filled($data['preferred_date'] ?? null) ? $data['preferred_date'] : null,
            'group_size' => $isGroup && filled($data['group_size'] ?? null) ? (int) $data['group_size'] : null,
            'message' => trim($data['message']),
        ]);

        $address = $this->settingsFor($operator)['email_notifications'] ? $this->notificationAddress($operator) : null;
        if ($address !== null) {
            Mail::to($address)->queue(new OperatorEnquiryMail($enquiry));
        }

        return $enquiry;
    }

    public function markRead(Operator $operator, string $enquiryId): void
    {
        $operator->enquiries()->whereKey($enquiryId)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function delete(Operator $operator, string $enquiryId): void
    {
        $operator->enquiries()->whereKey($enquiryId)->delete();
    }

    public function unreadCount(Operator $operator): int
    {
        return $operator->enquiries()->unread()->count();
    }

    /**
     * Reply on WhatsApp with a greeting filled in: the operator's usual channel.
     */
    public function whatsAppReplyUrl(Enquiry $enquiry): string
    {
        $operatorName = (string) $enquiry->operator->name;

        $message = $enquiry->isPrivateGroup()
            ? __('Hello :name, this is :operator. Thank you for your private trip request. ', ['name' => $enquiry->name, 'operator' => $operatorName])
            : __('Hello :name, this is :operator. Thank you for your message. ', ['name' => $enquiry->name, 'operator' => $operatorName]);

        return $this->whatsApp->buildWhatsAppUrl($enquiry->whatsapp, $message);
    }
}
