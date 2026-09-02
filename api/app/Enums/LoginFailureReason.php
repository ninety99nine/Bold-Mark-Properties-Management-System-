<?php

namespace App\Enums;

enum LoginFailureReason: string
{
    case USER_NOT_FOUND   = 'user_not_found';
    case WRONG_PASSWORD   = 'wrong_password';
    case ACCOUNT_INACTIVE = 'account_inactive';
    case ACCOUNT_INVITED  = 'account_invited';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
