<?php

namespace App\Support\Http;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** Streams rows as a CSV that opens correctly in Excel (UTF-8 with BOM). Used by every export. */
class CsvDownload
{
    /** @param iterable<array<int, mixed>> $rows */
    public static function make(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map([self::class, 'safe'], $headings));
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'safe'], $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Stops "CSV injection": a text cell starting with = + - @ (or a tab/CR) would run as a formula
     * in Excel. Customer and product names come from distributors' files, so they are not trusted.
     * Numbers, including negative ones, are left as numbers.
     */
    public static function safe(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && ! is_numeric($value) && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
