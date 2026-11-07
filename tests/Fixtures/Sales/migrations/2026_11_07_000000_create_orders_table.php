<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->string('reference');
            $table->string('status')->nullable();
            $table->string('channel')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('quantity');
            $table->boolean('gift');
            $table->dateTime('placed_at')->index();
            $table->date('delivered_on')->nullable();
            $table->timestamps();
        });
    }
};
