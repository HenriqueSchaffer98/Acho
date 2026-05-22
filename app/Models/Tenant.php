<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use App\Events\Tenant\TenantCreated;
use App\Events\Tenant\TenantReactivated;
use App\Events\Tenant\TenantSuspended;
use App\Events\Tenant\TenantUpdated;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'slug',
        'name',
        'custom_domain',
        'status',
    ];

    /** @var array<string, string|class-string> */
    protected $casts = [
        'status' => TenantStatus::class,
        'domain_verified_at' => 'datetime',
    ];

    /** @var array<string, class-string> */
    protected $dispatchesEvents = [
        'created' => TenantCreated::class,
        'updated' => TenantUpdated::class,
    ];

    public function suspend(): void
    {
        $this->update(['status' => TenantStatus::Suspended]);
        TenantSuspended::dispatch($this);
    }

    public function reactivate(): void
    {
        $this->update(['status' => TenantStatus::Active]);
        TenantReactivated::dispatch($this);
    }

    public function isActive(): bool
    {
        return $this->status === TenantStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === TenantStatus::Suspended;
    }
}
