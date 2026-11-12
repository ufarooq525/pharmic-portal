<?php

namespace App\Modules\Access\Enums;

use App\Support\Enums\HasOptions;

enum Role: string
{
    use HasOptions;

    case Owner = 'owner';
    case Accounts = 'accounts';
    case DataEntry = 'data_entry';
    case Viewer = 'viewer';
    case MedicalRep = 'medical_rep';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Accounts => 'Accounts',
            self::DataEntry => 'Data entry',
            self::Viewer => 'Viewer',
            self::MedicalRep => 'Medical rep',
        };
    }
}
