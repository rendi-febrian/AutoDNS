<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracked_domains', function (Blueprint $table) {
            $table->id();
            $table->string('domain_name');
            $table->string('zone_name');
            $table->foreignId('dns_record_id')->nullable()->constrained('dns_records')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique('domain_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_domains');
    }
};
