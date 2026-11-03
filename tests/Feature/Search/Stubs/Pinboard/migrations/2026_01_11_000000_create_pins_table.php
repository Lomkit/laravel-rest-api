<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pins', function (Blueprint $table): void {
            $table->id();
            $table->string('note');
            $table->nullableUuidMorphs('pinnable');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pins');
    }
};
