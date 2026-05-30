<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cloudflare_account_id')->constrained()->cascadeOnDelete();
            $table->string('zone_id');
            $table->string('name');
            $table->string('status')->default('active');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['cloudflare_account_id', 'zone_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zones');
    }
};
