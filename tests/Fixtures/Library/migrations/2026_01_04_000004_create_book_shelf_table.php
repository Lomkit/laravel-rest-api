<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_shelf', function (Blueprint $table): void {
            $table->foreignId('book_id')->constrained();
            $table->foreignId('shelf_id')->constrained();
        });
    }
};
