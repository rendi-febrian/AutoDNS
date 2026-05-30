<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CloudflareAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'api_token',
        'account_email',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class);
    }
}
