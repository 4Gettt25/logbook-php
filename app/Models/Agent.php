<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hostname',
        'ip_address',
        'environment',
        'status',
        'last_heartbeat',
        'telegraf_config',
        'tls_certificate',
        'api_token',
        'version',
        'os_info',
        'architecture',
        'metadata'
    ];

    protected $casts = [
        'last_heartbeat' => 'datetime',
        'telegraf_config' => 'array',
        'os_info' => 'array',
        'metadata' => 'array',
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';
    const STATUS_ERROR = 'error';
    const STATUS_PENDING = 'pending';

    public function logs(): HasMany
    {
        return $this->hasMany(Log::class);
    }

    public function metrics(): HasMany
    {
        return $this->hasMany(Metric::class);
    }

    public function isOnline(): bool
    {
        return $this->last_heartbeat && 
               $this->last_heartbeat->diffInMinutes(now()) <= 5;
    }

    public function getStatusBadgeColor(): string
    {
        return match($this->status) {
            self::STATUS_ACTIVE => $this->isOnline() ? 'success' : 'warning',
            self::STATUS_INACTIVE => 'gray',
            self::STATUS_ERROR => 'danger',
            self::STATUS_PENDING => 'info',
            default => 'gray'
        };
    }
}