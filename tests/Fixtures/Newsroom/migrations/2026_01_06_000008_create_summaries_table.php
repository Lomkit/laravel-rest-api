<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('summaries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained();
            $table->text('body');
        });
    }
};
