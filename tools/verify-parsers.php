<?php

/**
 * Standalone check of the PDF parsers (no framework needed):
 *   php tools/verify-parsers.php /path/to/file1.pdf /path/to/file2.pdf
 */
spl_autoload_register(function (string $class) {
    $file = __DIR__.'/../app/'.str_replace(['App\\', '\\'], ['', '/'], $class).'.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Modules\Imports\Parsers\PdfImportReader;

$reader = new PdfImportReader;
$failed = 0;

foreach (array_slice($argv, 1) as $path) {
    $file = $reader->read($path);
    $ok = $file->totalsMatch();
    $failed += $ok ? 0 : 1;
    printf("%-34s %-8s %s..%s customers=%3d lines=%3d units=%s/%s value=%s/%s %s\n",
        substr($file->distributorInFile, 0, 34), $file->layout, $file->periodFrom, $file->periodTo,
        count($file->customers), count($file->lines), $file->parsedUnits(), $file->fileUnits,
        round((float) $file->parsedValue(), 1), $file->fileValue, $ok ? 'OK' : 'MISMATCH');
}

exit($failed ? 1 : 0);
