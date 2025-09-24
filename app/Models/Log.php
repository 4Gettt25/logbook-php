<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'timestamp',
        'level',
        'message',
        'source',
        'facility',
        'hostname',
        'process_name',
        'process_id',
        'environment',
        'tags',
        'metadata',
        'elasticsearch_id'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'tags' => 'array',
        'metadata' => 'array',
    ];

    const LEVEL_EMERGENCY = 'emergency';
    const LEVEL_ALERT = 'alert';
    const LEVEL_CRITICAL = 'critical';
    const LEVEL_ERROR = 'error';
    const LEVEL_WARNING = 'warning';
    const LEVEL_NOTICE = 'notice';
    const LEVEL_INFO = 'info';
    const LEVEL_DEBUG = 'debug';

    const SOURCE_SYSLOG = 'syslog';
    const SOURCE_JOURNALD = 'journald';
    const SOURCE_NGINX = 'nginx';
    const SOURCE_APACHE = 'apache';
    const SOURCE_APPLICATION = 'application';
    const SOURCE_CUSTOM = 'custom';

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function getLevelColor(): string
    {
        return match($this->level) {
            self::LEVEL_EMERGENCY, self::LEVEL_ALERT, self::LEVEL_CRITICAL => 'danger',
            self::LEVEL_ERROR => 'warning',
            self::LEVEL_WARNING => 'yellow',
            self::LEVEL_NOTICE => 'info',
            self::LEVEL_INFO => 'primary',
            self::LEVEL_DEBUG => 'gray',
            default => 'gray'
        };
    }

    public function getLevelPriority(): int
    {
        return match($this->level) {
            self::LEVEL_EMERGENCY => 8,
            self::LEVEL_ALERT => 7,
            self::LEVEL_CRITICAL => 6,
            self::LEVEL_ERROR => 5,
            self::LEVEL_WARNING => 4,
            self::LEVEL_NOTICE => 3,
            self::LEVEL_INFO => 2,
            self::LEVEL_DEBUG => 1,
            default => 0
        };
    }
}