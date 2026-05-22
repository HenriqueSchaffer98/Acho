<?php

declare(strict_types=1);

namespace App\Jobs\Auth;

use App\Models\RefreshToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredTokens implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        $deleted = RefreshToken::where('expires_at', '<', now())->delete();

        Log::info('CleanupExpiredTokens executed', ['deleted' => $deleted]);
    }
}
