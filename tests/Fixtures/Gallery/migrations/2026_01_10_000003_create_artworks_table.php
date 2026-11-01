<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artworks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('artist_id')
                ->nullable()
                ->constrained();
            $table->string('title');
            $table->string('medium');
            $table->json('dimensions')->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->nullableMorphs('owner');
            $table->timestamps();
        });
    }
};
