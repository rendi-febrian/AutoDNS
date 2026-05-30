<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DnsRecord extends Model
{
    protected $fillable = [
        'zone_id',
        'record_id',
        'type',
        'name',
        'content',
        'proxied',
        'ttl',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'proxied' => 'boolean',
            'ttl' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function trackedDomain(): HasOne
    {
        return $this->hasOne(TrackedDomain::class);
    }
}
