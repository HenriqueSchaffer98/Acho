<?php

declare(strict_types=1);

use App\Exceptions\Auth\MissingPepperException;
use App\Support\Hashing\PepperedArgon2IdHasher;
use Illuminate\Hashing\Argon2IdHasher;
use Illuminate\Support\Facades\Hash;

it('uses argon2id as the default driver (ADR-023)', function () {
    expect(config('hashing.driver'))->toBe('argon2id-pepper');

    $hash = Hash::make('Senha-Forte-123');

    expect($hash)->toStartWith('$argon2id$');
});

it('uses the argon parameters required by ADR-023', function () {
    $hash = Hash::make('Senha-Forte-123');

    // ADR-023: memory=65536 (64MB), time=4, threads=1.
    expect($hash)->toContain('m=65536,t=4,p=1');
});

it('verifies a password it just hashed', function () {
    $hash = Hash::make('Senha-Forte-123');

    expect(Hash::check('Senha-Forte-123', $hash))->toBeTrue();
    expect(Hash::check('Senha-Errada-999', $hash))->toBeFalse();
});

it('applies the pepper so hashes are invalid without it', function () {
    $rawHasher = new Argon2IdHasher(config('hashing.argon'));

    $hashWithoutPepper = $rawHasher->make('Senha-Forte-123');
    $hashWithPepper = Hash::make('Senha-Forte-123');

    // Peppered hasher rejects hash made without pepper.
    expect(Hash::check('Senha-Forte-123', $hashWithoutPepper))->toBeFalse();

    // Raw hasher rejects hash made with pepper.
    expect($rawHasher->check('Senha-Forte-123', $hashWithPepper))->toBeFalse();
});

it('rejects construction without a pepper', function () {
    new PepperedArgon2IdHasher(config('hashing.argon'), '');
})->throws(MissingPepperException::class);

it('returns false on null or empty hash', function () {
    expect(Hash::check('Senha-Forte-123', null))->toBeFalse();
    expect(Hash::check('Senha-Forte-123', ''))->toBeFalse();
});

it('detects when a hash needs rehash after parameter changes', function () {
    $hash = Hash::make('Senha-Forte-123');

    // Same parameters: no rehash.
    expect(Hash::needsRehash($hash))->toBeFalse();

    // Stricter parameters: rehash needed.
    expect(Hash::needsRehash($hash, ['memory' => 131072, 'time' => 4, 'threads' => 1]))
        ->toBeTrue();
});
