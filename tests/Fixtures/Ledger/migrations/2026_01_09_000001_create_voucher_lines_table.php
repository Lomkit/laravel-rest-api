<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voucher_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id');
            $table->string('label');
            $table->timestamps();
        });
    }
};
