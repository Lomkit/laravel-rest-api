<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_tag', function (Blueprint $table): void {
            $table->foreignId('post_id')->constrained();
            $table->foreignId('tag_id')->constrained();
        });
    }
};
