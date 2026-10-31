<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('desk_id')->constrained();
            $table->foreignId('reporter_id')->nullable()->constrained();
            $table->string('headline');
            $table->timestamps();
        });
    }
};
