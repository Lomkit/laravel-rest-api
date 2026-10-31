<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->integer('number')->unique();
            $table->string('team');
            $table->string('title');
            $table->double('price');
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }
};
