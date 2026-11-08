<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shows', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->boolean('featured');
        });

        Schema::create('stages', function (Blueprint $table): void {
            $table->string('code')->primary();
            $table->string('name');
        });

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->string('body');
            $table->string('notable_type')->nullable();
            $table->string('notable_id')->nullable();
            $table->index(['notable_type', 'notable_id']);
        });

        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('taggables', function (Blueprint $table): void {
            $table->unsignedBigInteger('tag_id');
            $table->string('taggable_type');
            $table->string('taggable_id');
            $table->index(['taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('notes');
        Schema::dropIfExists('stages');
        Schema::dropIfExists('shows');
    }
};
