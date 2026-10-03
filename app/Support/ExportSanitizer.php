<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExportSanitizer
{
    public static function csvValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $string = (string) $value;
        if ($string === '') {
            return $string;
        }

        return self::needsFormulaNeutralization($string) ? "'" . $string : $string;
    }

    public static function writeString(Worksheet $sheet, string $cell, mixed $value): void
    {
        $sheet->setCellValueExplicit($cell, (string) self::csvValue($value), DataType::TYPE_STRING);
    }

    private static function needsFormulaNeutralization(string $value): bool
    {
        $first = mb_substr($value, 0, 1);

        return in_array($first, ['=', '+', '-', '@', "\t", "\r"], true);
    }
}
