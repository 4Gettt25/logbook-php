<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Metric extends Model
{
    use HasFactory;

    protected $fillable = [
        'agent_id',
        'timestamp',
        'measurement',
        'field_key',
        'field_value',
        'tags',
        'environment',
        'metadata',
        'influxdb_timestamp'
    ];

    protected $casts = [
        'timestamp' => 'datetime',
        'field_value' => 'float',
        'tags' => 'array',
        'metadata' => 'array',
        'influxdb_timestamp' => 'integer'
    ];

    const MEASUREMENT_CPU = 'cpu';
    const MEASUREMENT_MEMORY = 'memory';
    const MEASUREMENT_DISK = 'disk';
    const MEASUREMENT_NETWORK = 'network';
    const MEASUREMENT_LOAD = 'load';
    const MEASUREMENT_PROCESS = 'process';
    const MEASUREMENT_CUSTOM = 'custom';

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function getFormattedValue(): string
    {
        return match($this->measurement) {
            self::MEASUREMENT_CPU => number_format($this->field_value, 2) . '%',
            self::MEASUREMENT_MEMORY => $this->formatBytes($this->field_value),
            self::MEASUREMENT_DISK => $this->formatBytes($this->field_value),
            self::MEASUREMENT_NETWORK => $this->formatBytes($this->field_value) . '/s',
            self::MEASUREMENT_LOAD => number_format($this->field_value, 2),
            default => (string) $this->field_value
        };
    }

    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes) / log(1024));
        return number_format($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }

    public function getMeasurementIcon(): string
    {
        return match($this->measurement) {
            self::MEASUREMENT_CPU => 'heroicon-o-cpu-chip',
            self::MEASUREMENT_MEMORY => 'heroicon-o-circle-stack',
            self::MEASUREMENT_DISK => 'heroicon-o-server-stack',
            self::MEASUREMENT_NETWORK => 'heroicon-o-signal',
            self::MEASUREMENT_LOAD => 'heroicon-o-chart-bar',
            self::MEASUREMENT_PROCESS => 'heroicon-o-cog',
            default => 'heroicon-o-chart-bar'
        };
    }
}