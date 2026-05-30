<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackedDomain extends Model
{
    use HasFactory;
    protected $fillable = [
        'domain_name',
        'zone_name',
        'dns_record_id',
        'ip_address',
        'is_active',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function dnsRecord(): BelongsTo
    {
        return $this->belongsTo(DnsRecord::class);
    }

    public function updateLogs(): HasMany
    {
        return $this->hasMany(DnsUpdateLog::class);
    }
}
