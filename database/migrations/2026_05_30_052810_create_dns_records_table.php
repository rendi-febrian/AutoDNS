<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dns_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->string('record_id');
            $table->string('type', 10);
            $table->string('name');
            $table->text('content');
            $table->boolean('proxied')->default(false);
            $table->integer('ttl')->default(120);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['zone_id', 'record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dns_records');
    }
};
