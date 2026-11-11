<?php

namespace App\Modules\Distribution\Models;

use App\Modules\Imports\Models\Import;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A distributor and the profile of its monthly file: which report layout its software prints, the
 * name at the top of the PDF, and where the bonus figure comes from. Every distributor sends a
 * different format, so these checks keep a wrong or changed file from being posted silently.
 */
class Distributor extends Model
{
    public const LAYOUTS = [
        'matrix' => 'Product grid (SoftTouch "Customer wise sales report")',
        'lineitem' => 'Item lines with bonus (SoftTouch "Sale Report")',
        'monthly' => 'Jan–Dec columns (customer annual sales)',
    ];

    public const BONUS_FILE = 'file';
    public const BONUS_SCHEME = 'scheme';
    public const BONUS_NONE = 'none';

    public const BONUS_SOURCES = [
        self::BONUS_FILE => 'Use the bonus printed in the file; the product scheme where none is printed',
        self::BONUS_SCHEME => 'Always use the product scheme, ignore the file',
        self::BONUS_NONE => 'Only the bonus printed in the file; no scheme bonus',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['opening_balance' => 'float'];
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function imports(): HasMany
    {
        return $this->hasMany(Import::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(ProductAlias::class);
    }

    /** Bonus units for one line, following this distributor's bonus setting. */
    public function bonusFor(?float $inFile, float $scheme): float
    {
        return match ($this->bonus_source) {
            self::BONUS_SCHEME => $scheme,
            self::BONUS_NONE => $inFile ?? 0.0,
            default => $inFile ?? $scheme,
        };
    }

    /** True when the bonus posted for a line is the one printed in the file. */
    public function bonusIsFromFile(?float $inFile): bool
    {
        return $inFile !== null && $this->bonus_source !== self::BONUS_SCHEME;
    }

    /** Does the name printed at the top of a file belong to this distributor? */
    public function headerMatches(string $inFile): bool
    {
        $file = self::squash($inFile);

        if ($this->header_name) {
            return str_contains($file, self::squash($this->header_name));
        }

        // No saved header yet: the first word of the name ("HAMDAN", "UMAR") is enough.
        return str_contains($file, self::squash(Str::before($this->name, ' ')));
    }

    private static function squash(string $text): string
    {
        return preg_replace('/[^A-Z0-9]+/', '', mb_strtoupper($text));
    }
}
