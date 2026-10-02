<?php

namespace App\Services;

/**
 * One rule for turning what guests and operators type into a phone number we can use.
 */
final class PhoneNumber
{
    /**
     * Digits only, Indonesian leading 0 converted to 62 (wa.me / matching format).
     */
    public static function normalize(?string $phone): string
    {
        $digits = self::digits($phone);

        if ($digits !== '' && str_starts_with($digits, '0')) {
            return '62'.substr($digits, 1);
        }

        return $digits;
    }

    /**
     * Digits only, as typed.
     */
    public static function digits(?string $phone): string
    {
        return (string) preg_replace('/\D+/', '', (string) $phone);
    }

    /**
     * Direct wa.me chat link, or null when there is no usable number.
     */
    public static function whatsAppLink(?string $phone, ?string $text = null): ?string
    {
        $number = self::normalize($phone);

        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.($text !== null && $text !== '' ? '?text='.rawurlencode($text) : '');
    }
}
