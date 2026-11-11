<?php

namespace App\Modules\Imports\Parsers;

use RuntimeException;

/** Thin wrapper around poppler's pdftotext (apt: poppler-utils). */
final class PdfText
{
    public const NUM = '-?\d+(?:,\d{3})*(?:\.\d+)?';

    public static function layout(string $path): string
    {
        return self::run([self::binary(), '-layout', $path, '-']);
    }

    /**
     * Words with their position on the page, grouped into visual lines.
     *
     * @return list<list<list<array{x0: float, x1: float, top: float, text: string}>>> pages -> lines -> words
     */
    public static function wordLines(string $path): array
    {
        $xml = self::run([self::binary(), '-bbox', $path, '-']);
        $pages = [];

        foreach (preg_split('/<page\b[^>]*>/', $xml) as $i => $chunk) {
            if ($i === 0) {
                continue;
            }

            preg_match_all('/<word xMin="([\d.]+)" yMin="([\d.]+)" xMax="([\d.]+)" yMax="[\d.]+">(.*?)<\/word>/s', $chunk, $m, PREG_SET_ORDER);
            $rows = [];

            foreach ($m as $w) {
                $word = ['x0' => (float) $w[1], 'top' => (float) $w[2], 'x1' => (float) $w[3], 'text' => html_entity_decode($w[4], ENT_QUOTES | ENT_XML1)];
                $key = null;

                foreach ($rows as $k => $_) {
                    if (abs((float) $k - $word['top']) < 2.5) {
                        $key = $k;
                        break;
                    }
                }

                $rows[$key ?? (string) $word['top']][] = $word;
            }

            uksort($rows, fn ($a, $b) => (float) $a <=> (float) $b);
            $pages[] = array_map(function (array $words) {
                usort($words, fn ($a, $b) => $a['x0'] <=> $b['x0']);

                return $words;
            }, array_values($rows));
        }

        return $pages;
    }

    public static function number(string $value): float
    {
        return (float) str_replace(',', '', $value);
    }

    /** PDFTOTEXT_PATH in .env points at the binary when it is not on PATH (e.g. C:/poppler/Library/bin/pdftotext.exe). */
    private static function binary(): string
    {
        // From config inside the app (works with config:cache); straight from the environment when the
        // parser runs on its own, e.g. the sample-file tests.
        $configured = class_exists(\Illuminate\Container\Container::class) && \Illuminate\Container\Container::getInstance()->bound('config')
            ? config('pharmic.pdftotext') : null;

        return $configured ?: (($_SERVER['PDFTOTEXT_PATH'] ?? '') ?: (($_ENV['PDFTOTEXT_PATH'] ?? '') ?: (getenv('PDFTOTEXT_PATH') ?: 'pdftotext')));
    }

    /** @param list<string> $command */
    private static function run(array $command): string
    {
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('pdftotext is not installed. Install poppler-utils, or set PDFTOTEXT_PATH in .env.');
        }

        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        if (proc_close($process) !== 0) {
            throw new RuntimeException('Could not read the PDF: '.trim($err));
        }

        return $out;
    }
}
