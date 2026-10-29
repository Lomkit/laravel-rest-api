<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('featured_products', function (Blueprint $table): void {
            $table->foreignId('category_id')->constrained();
            $table->foreignId('product_id')->constrained();
            $table->unsignedInteger('price');
        });
    }
};
