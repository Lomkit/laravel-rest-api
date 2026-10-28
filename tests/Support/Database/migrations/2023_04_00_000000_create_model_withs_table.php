<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Tests\Support\Models\BelongsToRelation;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('model_withs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('number');
            $table->string('string')->nullable();
            $table->string('unique')->unique()->nullable();
            $table->foreignIdFor(BelongsToRelation::class)->nullable()->constrained();
            $table->timestamps();
        });
    }
};
