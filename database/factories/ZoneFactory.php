<?php

namespace Database\Factories;

use App\Models\CloudflareAccount;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    public function definition(): array
    {
        return [
            'cloudflare_account_id' => CloudflareAccount::factory(),
            'zone_id' => fake()->uuid(),
            'name' => fake()->domainName(),
            'status' => 'active',
            'synced_at' => now(),
        ];
    }
}
