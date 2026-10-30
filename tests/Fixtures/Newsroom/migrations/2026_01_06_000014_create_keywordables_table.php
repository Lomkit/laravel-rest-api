<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('keywordables', function (Blueprint $table): void {
            $table->foreignId('keyword_id')->constrained();
            $table->morphs('keywordable');
        });
    }
};
