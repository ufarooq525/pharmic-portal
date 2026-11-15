<?php

namespace App\Modules\Access\Enums;

use App\Support\Enums\HasOptions;

/**
 * Staff logins get a role (and optional extra permissions). A medical rep login is tied to a rep
 * record and only ever sees "My territory" and their own payslip, whatever any permission says.
 */
enum LoginType: string
{
    use HasOptions;

    case Staff = 'staff';
    case MedicalRep = 'medical_rep';

    public function label(): string
    {
        return match ($this) {
            self::Staff => 'Staff',
            self::MedicalRep => 'Medical rep',
        };
    }
}
