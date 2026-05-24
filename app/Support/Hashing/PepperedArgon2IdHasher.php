<?php

declare(strict_types=1);

namespace App\Support\Hashing;

use App\Exceptions\Auth\MissingPepperException;
use Illuminate\Hashing\Argon2IdHasher;

/**
 * Argon2id hasher with global pepper (ADR-023).
 *
 * The pepper is concatenated to the password before hashing/verification.
 * It lives in the env (vault in prod), never in the database.
 */
class PepperedArgon2IdHasher extends Argon2IdHasher
{
    private readonly string $pepper;

    /** @param  array<string, mixed>  $options */
    public function __construct(array $options, string $pepper)
    {
        parent::__construct($options);

        if ($pepper === '') {
            throw new MissingPepperException(
                'APP_PEPPER is not set. ADR-023 requires a pepper for password hashing.',
            );
        }

        $this->pepper = $pepper;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function make(#[\SensitiveParameter] $value, array $options = [])
    {
        return parent::make($this->season((string) $value), $options);
    }

    /**
     * @param  array<string, mixed>  $options
     */
    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = [])
    {
        return parent::check($this->season((string) $value), $hashedValue, $options);
    }

    private function season(string $value): string
    {
        return $value . $this->pepper;
    }
}
