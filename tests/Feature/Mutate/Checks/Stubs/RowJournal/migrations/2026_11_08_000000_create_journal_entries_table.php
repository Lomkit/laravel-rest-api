<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('subject');
            $table->string('note');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
    }
};
