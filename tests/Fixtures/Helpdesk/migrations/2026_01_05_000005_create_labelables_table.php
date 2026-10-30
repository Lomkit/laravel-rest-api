<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labelables', function (Blueprint $table): void {
            $table->foreignId('tag_id')->constrained();
            $table->morphs('labelable');
            $table->unsignedInteger('position')->nullable();
        });
    }
};
