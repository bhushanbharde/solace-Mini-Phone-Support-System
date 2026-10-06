<?php
declare(strict_types=1);

final class PhoneNormalizer
{
    public static function normalize(string $phone): string
    {
        // For this assignment we keep +, digits, and an optional leading country code.
        // In production, use a proper E.164 phone-number library.
        $normalized = preg_replace('/[^\d+]/', '', trim($phone)) ?? '';

        if ($normalized === '') {
            throw new InvalidArgumentException('Invalid phone number.');
        }

        return $normalized;
    }
}
