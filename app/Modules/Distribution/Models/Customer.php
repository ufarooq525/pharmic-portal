<?php

namespace App\Modules\Distribution\Models;

use App\Modules\Imports\Parsers\PdfImportReader;
use App\Modules\Sales\Models\SaleLine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['first_seen' => 'date', 'last_seen' => 'date'];
    }

    public function distributor(): BelongsTo
    {
        return $this->belongsTo(Distributor::class);
    }

    public function saleLines(): HasMany
    {
        return $this->hasMany(SaleLine::class);
    }

    /** The register key: the distributor's customer code, or normalised name + area when the layout prints no codes. */
    public static function matchKey(?string $code, string $name, ?string $area): string
    {
        return $code ?: PdfImportReader::normalise($name).'|'.($area ?? '');
    }

    /** A customer can be shared between reps; share_percent splits the credit. */
    public function medicalReps(): BelongsToMany
    {
        return $this->belongsToMany(MedicalRep::class)->withPivot('share_percent')->withTimestamps();
    }
}
