<?php

namespace Database\Factories;

use App\Models\DnsUpdateLog;
use App\Models\TrackedDomain;
use Illuminate\Database\Eloquent\Factories\Factory;

class DnsUpdateLogFactory extends Factory
{
    protected $model = DnsUpdateLog::class;

    public function definition(): array
    {
        return [
            'tracked_domain_id' => TrackedDomain::factory(),
            'zone_name' => fake()->domainName(),
            'record_name' => fake()->domainName(),
            'record_type' => 'A',
            'old_ip' => fake()->localIpv4(),
            'new_ip' => fake()->localIpv4(),
            'status' => 'success',
            'response_message' => 'Updated successfully',
        ];
    }
}
