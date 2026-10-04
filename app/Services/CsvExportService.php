<?php

namespace App\Services;

/**
 * Writes CSV rows for every export (guests, coupons, admin reports) so text typed by guests
 * or operators can never run as a spreadsheet formula when the file is opened in Excel or
 * Google Sheets (CSV / formula injection).
 */
class CsvExportService
{
    /**
     * @param  resource  $handle
     * @param  array<int, mixed>  $row
     */
    public static function writeRow($handle, array $row): void
    {
        fputcsv($handle, array_map(self::safeCell(...), $row), escape: '');
    }

    /**
     * Neutralise a cell that a spreadsheet would treat as a formula. Plain numbers and phone
     * numbers (e.g. "+62 812-3456") are left as they are.
     */
    public static function safeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        if (! in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return $value;
        }

        if (preg_match('/^[+-]?[\d\s().-]+$/', $value) === 1) {
            return $value;
        }

        return "'".$value;
    }
}
