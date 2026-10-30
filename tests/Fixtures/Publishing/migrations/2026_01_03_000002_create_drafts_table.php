<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('author_id')->constrained();
            $table->string('title');
            $table->timestamps();
        });
    }
};
