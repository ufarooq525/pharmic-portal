<?php

namespace App\Modules\Imports\Parsers;

interface LayoutParser
{
    public function layout(): string;

    /** True when the plain text of the PDF looks like this layout. */
    public function matches(string $text): bool;

    /**
     * @return array{lines: list<array<string, mixed>>, customers: list<array<string, mixed>>, units: ?float, value: ?float, columns?: list<array<string, mixed>>}
     */
    public function parse(string $path, string $text): array;
}
