<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_topic', function (Blueprint $table): void {
            $table->foreignId('story_id')->constrained();
            $table->foreignId('topic_id')->constrained();
            $table->unsignedInteger('position')->default(0);
            $table->string('note', 80)->nullable();
            $table->boolean('pinned');
            $table->binary('badge')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }
};
