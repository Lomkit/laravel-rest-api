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
        Schema::create('inversables', function (Blueprint $table) {
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\Model::class)->constrained();
            $table->morphs('inversable');
            $table->integer('number')->default(0);

            $table->timestamps();

            $table->primary(['model_id', 'inversable_id', 'inversable_type']);
        });
    }
};