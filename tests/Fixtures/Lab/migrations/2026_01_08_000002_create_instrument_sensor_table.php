<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrument_sensor', function (Blueprint $table): void {
            $table->foreignId('instrument_id')->constrained();
            $table->foreignId('sensor_id')->constrained();
            $table->json('wiring')->nullable();
        });
    }
};
