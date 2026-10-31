<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained();
            $table->foreignId('parent_id')->nullable()->constrained('comments');
            $table->text('body');
        });
    }
};
