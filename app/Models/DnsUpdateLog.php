<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DnsUpdateLog extends Model
{
    use HasFactory;
    protected $fillable = [
        'tracked_domain_id',
        'zone_name',
        'record_name',
        'record_type',
        'old_ip',
        'new_ip',
        'status',
        'response_message',
    ];

    public function trackedDomain(): BelongsTo
    {
        return $this->belongsTo(TrackedDomain::class);
    }
}
