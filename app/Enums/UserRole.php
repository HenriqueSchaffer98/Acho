<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Corretor = 'corretor';
    case Cliente = 'cliente';
    case SuperAdmin = 'super_admin';
}
