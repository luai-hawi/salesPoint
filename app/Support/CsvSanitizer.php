<?php

namespace App\Support;

class CsvSanitizer
{
    public static function cell(mixed $value): mixed
    {
        return ExportSanitizer::csvValue($value);
    }
}
