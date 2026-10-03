<?php

namespace App\Services\WebAuthn;

class Cbor
{
    private const MAX_INPUT_BYTES = 16384;

    private const MAX_DEPTH = 16;

    private const MAX_ITEMS = 256;

    public static function decode(string $data, int &$offset = 0): mixed
    {
        if (strlen($data) > self::MAX_INPUT_BYTES) {
            throw new WebAuthnException('invalid_cbor');
        }

        try {
            return self::decodeValue($data, $offset, 0);
        } catch (WebAuthnException $e) {
            throw $e;
        } catch (\Throwable) {
            throw new WebAuthnException('invalid_cbor');
        }
    }

    public static function encode(mixed $value): string
    {
        if ($value === null) {
            return chr(0xf6);
        }

        if ($value === false) {
            return chr(0xf4);
        }

        if ($value === true) {
            return chr(0xf5);
        }

        if (is_int($value)) {
            return $value >= 0
                ? self::encodeLength(0, $value)
                : self::encodeLength(1, (-1 - $value));
        }

        if (is_string($value)) {
            return self::encodeLength(3, strlen($value)) . $value;
        }

        if (is_array($value)) {
            if (array_is_list($value)) {
                $encoded = self::encodeLength(4, count($value));
                foreach ($value as $item) {
                    $encoded .= self::encode($item);
                }

                return $encoded;
            }

            $encoded = self::encodeLength(5, count($value));
            foreach ($value as $key => $item) {
                $encoded .= self::encode($key);
                $encoded .= self::encode($item);
            }

            return $encoded;
        }

        throw new WebAuthnException('invalid_cbor');
    }

    private static function readLength(string $data, int &$offset, int $additional): int
    {
        return match (true) {
            $additional < 24 => $additional,
            $additional === 24 => ord(self::slice($data, $offset, 1)),
            $additional === 25 => unpack('n', self::slice($data, $offset, 2))[1],
            $additional === 26 => unpack('N', self::slice($data, $offset, 4))[1],
            default => throw new WebAuthnException('invalid_cbor'),
        };
    }

    private static function slice(string $data, int &$offset, int $length): string
    {
        if ($length < 0 || $length > self::MAX_INPUT_BYTES) {
            throw new WebAuthnException('invalid_cbor');
        }

        $slice = substr($data, $offset, $length);
        if (strlen($slice) !== $length) {
            throw new WebAuthnException('invalid_cbor');
        }

        $offset += $length;

        return $slice;
    }

    /**
     * @return list<mixed>
     */
    private static function decodeArray(string $data, int &$offset, int $length, int $depth): array
    {
        if ($length > self::MAX_ITEMS) {
            throw new WebAuthnException('invalid_cbor');
        }

        $items = [];
        for ($i = 0; $i < $length; $i++) {
            $items[] = self::decodeValue($data, $offset, $depth + 1);
        }

        return $items;
    }

    /**
     * @return array<mixed, mixed>
     */
    private static function decodeMap(string $data, int &$offset, int $length, int $depth): array
    {
        if ($length > self::MAX_ITEMS) {
            throw new WebAuthnException('invalid_cbor');
        }

        $map = [];
        for ($i = 0; $i < $length; $i++) {
            $key = self::decodeValue($data, $offset, $depth + 1);
            if (! is_int($key) && ! is_string($key)) {
                throw new WebAuthnException('invalid_cbor');
            }

            $map[$key] = self::decodeValue($data, $offset, $depth + 1);
        }

        return $map;
    }

    private static function decodeSimple(int $additional): mixed
    {
        return match ($additional) {
            20 => false,
            21 => true,
            22 => null,
            default => throw new WebAuthnException('invalid_cbor'),
        };
    }

    private static function encodeLength(int $major, int $value): string
    {
        if ($value < 24) {
            return chr(($major << 5) | $value);
        }

        if ($value < 256) {
            return chr(($major << 5) | 24) . chr($value);
        }

        if ($value < 65536) {
            return chr(($major << 5) | 25) . pack('n', $value);
        }

        return chr(($major << 5) | 26) . pack('N', $value);
    }

    private static function decodeValue(string $data, int &$offset, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH || ! isset($data[$offset])) {
            throw new WebAuthnException('invalid_cbor');
        }

        $initial = ord($data[$offset++]);
        $major = $initial >> 5;
        $additional = $initial & 0x1f;
        $length = self::readLength($data, $offset, $additional);

        return match ($major) {
            0 => $length,
            1 => -1 - $length,
            2 => self::slice($data, $offset, $length),
            3 => self::slice($data, $offset, $length),
            4 => self::decodeArray($data, $offset, $length, $depth),
            5 => self::decodeMap($data, $offset, $length, $depth),
            6 => self::decodeValue($data, $offset, $depth + 1),
            7 => self::decodeSimple($additional),
            default => throw new WebAuthnException('invalid_cbor'),
        };
    }
}
