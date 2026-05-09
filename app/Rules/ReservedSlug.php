<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ReservedSlug implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $reserved = config('reserved_slugs', []);

        if (in_array(strtolower((string) $value), $reserved, strict: true)) {
            $fail('O subdomínio ":input" é reservado e não pode ser utilizado.');
        }
    }
}
