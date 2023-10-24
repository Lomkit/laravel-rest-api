<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration {
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
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\BelongsToRelation::class)->nullable()->constrained();
            $table->timestamps();
        });
    }
};
