<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stages', function (Blueprint $table): void {
            $table->string('code')->primary();
            $table->string('name');
        });

        Schema::create('shows', function (Blueprint $table): void {
            $table->id();
            $table->string('stage_code')->nullable();
            $table->string('title');
            $table->string('summary')->nullable();
            $table->decimal('price', 8, 2)->nullable();
            $table->unsignedInteger('seats')->default(0);
            $table->boolean('featured')->default(false);
            $table->date('starts_on');
            $table->dateTime('starts_at')->nullable();
            $table->string('status');
            $table->unsignedTinyInteger('level')->default(1);
            $table->json('settings')->nullable();
            $table->string('secret')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('artists', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('artist_show', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('artist_id');
            $table->foreignId('show_id');
            $table->string('role')->nullable();
            $table->boolean('headliner')->default(false);
            $table->dateTime('confirmed_at')->nullable();
        });

        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('show_id');
            $table->unsignedTinyInteger('rating');
            $table->string('body');
        });

        Schema::create('notes', function (Blueprint $table): void {
            $table->id();
            $table->string('notable_type');
            $table->string('notable_id');
            $table->string('body');
        });

        Schema::create('labels', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('labelables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('label_id');
            $table->morphs('labelable');
            $table->unsignedInteger('position')->nullable();
        });
    }
};
