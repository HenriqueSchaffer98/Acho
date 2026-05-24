<?php

declare(strict_types=1);

namespace App\Exceptions\Auth;

use Exception;

class EmailAlreadyRegisteredException extends Exception
{
    public function __construct(string $message = 'Já existe uma conta com este e-mail neste tenant.')
    {
        parent::__construct($message);
    }
}
