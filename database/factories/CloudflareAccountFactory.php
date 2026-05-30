<?php

namespace Database\Factories;

use App\Models\CloudflareAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class CloudflareAccountFactory extends Factory
{
    protected $model = CloudflareAccount::class;

    public function definition(): array
    {
        return [
            'name' => fake()->userName(),
            'api_token' => 'cf_' . fake()->sha256(),
            'account_email' => fake()->email(),
            'is_active' => true,
        ];
    }
}
