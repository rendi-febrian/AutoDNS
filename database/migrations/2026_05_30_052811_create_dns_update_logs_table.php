<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_update_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_domain_id')->nullable()->constrained()->nullOnDelete();
            $table->string('zone_name');
            $table->string('record_name');
            $table->string('record_type', 10)->default('A');
            $table->string('old_ip')->nullable();
            $table->string('new_ip');
            $table->string('status');
            $table->text('response_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_update_logs');
    }
};
