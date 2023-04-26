<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\BelongsToRelation::class)->constrained();
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\HasOneRelation::class)->constrained();
            $table->timestamps();
        });
    }
};