<?php

namespace App\Support;

/**
 * One cell of an exported CSV, made safe to open in a spreadsheet.
 *
 * Names on the public pages are typed by anyone, and the director opens the
 * export in Excel. A "name" beginning with = + - @ (or a tab or carriage
 * return) would be read as a formula — a link that leaks the neighbouring
 * cells, or worse. A leading apostrophe makes the spreadsheet show it as text.
 */
final class CsvCell
{
    public static function safe(mixed $value): string
    {
        $text = (string) $value;

        return $text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'".$text
            : $text;
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    public static function row(array $row): array
    {
        return array_map(self::safe(...), $row);
    }
}
