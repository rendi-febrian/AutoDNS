<?php

namespace Database\Factories;

use App\Models\TrackedDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

class TrackedDomainFactory extends Factory
{
    protected $model = TrackedDomain::class;

    public function definition(): array
    {
        return [
            'domain_name' => fake()->unique()->domainName(),
            'zone_name' => fake()->domainName(),
            'ip_address' => fake()->localIpv4(),
            'is_active' => true,
            'last_synced_at' => now(),
        ];
    }
}
