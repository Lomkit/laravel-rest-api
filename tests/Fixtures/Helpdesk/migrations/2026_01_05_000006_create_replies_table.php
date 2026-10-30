<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained();
            $table->string('body');
            $table->timestamps();
        });
    }
};
