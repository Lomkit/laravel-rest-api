<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('summary')->nullable();
            $table->string('status');
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('views')->default(0);
            $table->decimal('rating', 3, 1)->nullable();
            $table->date('published_on');
            $table->dateTime('published_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }
};
