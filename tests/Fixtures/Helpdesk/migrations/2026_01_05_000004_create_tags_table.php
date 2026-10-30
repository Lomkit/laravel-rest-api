<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tag_ticket', function (Blueprint $table): void {
            $table->foreignId('tag_id')->constrained();
            $table->foreignId('ticket_id')->constrained();
            $table->unsignedInteger('position')->nullable();
        });
    }
};
