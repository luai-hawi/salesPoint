<?php

namespace App\Services\WebAuthn;

class CoseKey
{
    /**
     * @param  array<int|string, mixed>  $coseKey
     */
    public static function toPem(array $coseKey): string
    {
        $kty = (int) ($coseKey[1] ?? 0);
        $alg = (int) ($coseKey[3] ?? 0);

        return match ($kty) {
            2 => self::ec2ToPem($coseKey, $alg),
            3 => self::rsaToPem($coseKey, $alg),
            default => throw new WebAuthnException('unsupported_algorithm'),
        };
    }

    /**
     * @param  array<int|string, mixed>  $coseKey
     */
    public static function algorithm(array $coseKey): int
    {
        return (int) ($coseKey[3] ?? 0);
    }

    /**
     * @param  array<int|string, mixed>  $coseKey
     */
    private static function ec2ToPem(array $coseKey, int $alg): string
    {
        if ($alg !== -7 || (int) ($coseKey[-1] ?? 0) !== 1) {
            throw new WebAuthnException('unsupported_algorithm');
        }

        $x = $coseKey[-2] ?? null;
        $y = $coseKey[-3] ?? null;

        if (! is_string($x) || ! is_string($y)) {
            throw new WebAuthnException('invalid_public_key');
        }

        $point = "\x04" . $x . $y;
        $spki = hex2bin('3059301306072A8648CE3D020106082A8648CE3D030107034200') . $point;

        return self::pem('PUBLIC KEY', $spki);
    }

    /**
     * @param  array<int|string, mixed>  $coseKey
     */
    private static function rsaToPem(array $coseKey, int $alg): string
    {
        if ($alg !== -257) {
            throw new WebAuthnException('unsupported_algorithm');
        }

        $n = $coseKey[-1] ?? null;
        $e = $coseKey[-2] ?? null;

        if (! is_string($n) || ! is_string($e)) {
            throw new WebAuthnException('invalid_public_key');
        }

        $rsaKey = self::sequence(
            self::integer($n),
            self::integer($e),
        );

        $spki = self::sequence(
            self::sequence(
                self::oid('1.2.840.113549.1.1.1'),
                self::null(),
            ),
            self::bitString($rsaKey),
        );

        return self::pem('PUBLIC KEY', $spki);
    }

    private static function integer(string $value): string
    {
        $value = ltrim($value, "\x00");
        if ($value === '' || (ord($value[0]) & 0x80)) {
            $value = "\x00" . $value;
        }

        return "\x02" . self::length(strlen($value)) . $value;
    }

    private static function sequence(string ...$parts): string
    {
        $data = implode('', $parts);

        return "\x30" . self::length(strlen($data)) . $data;
    }

    private static function bitString(string $value): string
    {
        $data = "\x00" . $value;

        return "\x03" . self::length(strlen($data)) . $data;
    }

    private static function null(): string
    {
        return "\x05\x00";
    }

    private static function oid(string $oid): string
    {
        $parts = array_map('intval', explode('.', $oid));
        $first = (40 * array_shift($parts)) + array_shift($parts);
        $encoded = chr($first);

        foreach ($parts as $part) {
            $stack = [chr($part & 0x7f)];
            while ($part > 0x7f) {
                $part >>= 7;
                array_unshift($stack, chr(($part & 0x7f) | 0x80));
            }
            $encoded .= implode('', $stack);
        }

        return "\x06" . self::length(strlen($encoded)) . $encoded;
    }

    private static function length(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = ltrim(pack('N', $length), "\x00");

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function pem(string $type, string $der): string
    {
        return "-----BEGIN {$type}-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END {$type}-----\n";
    }
}
