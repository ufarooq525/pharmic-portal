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
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
