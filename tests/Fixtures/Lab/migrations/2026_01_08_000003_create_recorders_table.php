<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recorders', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->json('profile')->nullable();
            $table->json('samples')->nullable();
            $table->json('limits')->nullable();
            $table->json('notes')->nullable();
        });
    }
};
