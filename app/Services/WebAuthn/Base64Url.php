<?php

namespace App\Services\WebAuthn;

class Base64Url
{
    public static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function decode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new WebAuthnException('invalid_encoding');
        }

        return $decoded;
    }
}
