<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artwork_exhibition', function (Blueprint $table): void {
            $table->foreignId('artwork_id')->constrained();
            $table->foreignId('exhibition_id')->constrained();
            $table->unsignedInteger('position')->nullable();
            $table->string('wall')->nullable();
        });
    }
};
