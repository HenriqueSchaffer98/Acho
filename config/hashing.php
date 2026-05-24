<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | Argon2id with global pepper (ADR-023). The pepper is concatenated to
    | the password before hashing/verification. Storing it outside the DB
    | provides an extra defense layer against database leaks.
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id-pepper'),

    /*
    |--------------------------------------------------------------------------
    | Argon Options (ADR-023)
    |--------------------------------------------------------------------------
    |
    | memory: 64 MB — forces real RAM cost; defeats GPU clusters.
    | time:   4     — ~200ms on a modern CPU; tuned against brute force.
    | threads: 1    — single-threaded for predictability.
    |
    */

    'argon' => [
        'memory' => (int) env('HASH_ARGON_MEMORY', 65536),
        'threads' => (int) env('HASH_ARGON_THREADS', 1),
        'time' => (int) env('HASH_ARGON_TIME', 4),
        'verify' => true,
    ],

    'bcrypt' => [
        'rounds' => (int) env('BCRYPT_ROUNDS', 12),
        'verify' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pepper
    |--------------------------------------------------------------------------
    |
    | Secret string appended to passwords before hashing. Lives only in env
    | (vault in prod). Loss = nobody can log in. Backup separately (ADR-023).
    |
    */

    'pepper' => env('APP_PEPPER', ''),

    'rehash_on_login' => true,

];
