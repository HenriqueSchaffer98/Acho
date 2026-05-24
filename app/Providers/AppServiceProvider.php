<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\Auth\CleanupExpiredTokens;
use App\Support\Hashing\PepperedArgon2IdHasher;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerPasswordPolicy();
        $this->registerPepperedHasher();
        $this->registerSchedule();
    }

    private function registerSchedule(): void
    {
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);
            $schedule->job(new CleanupExpiredTokens)->daily();
        });
    }

    private function registerPasswordPolicy(): void
    {
        Password::defaults(function (): Password {
            return Password::min(8)
                ->letters()
                ->numbers()
                ->uncompromised();
        });
    }

    private function registerPepperedHasher(): void
    {
        Hash::extend('argon2id-pepper', function ($app): Hasher {
            /** @var array<string, mixed> $options */
            $options = $app['config']->get('hashing.argon', []);
            $pepper = (string) $app['config']->get('hashing.pepper', '');

            return new PepperedArgon2IdHasher($options, $pepper);
        });
    }
}
