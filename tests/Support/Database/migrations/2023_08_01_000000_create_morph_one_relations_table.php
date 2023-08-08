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
        Schema::create('morph_one_relations', function (Blueprint $table) {
            $table->id();
            $table->morphs('morph_one_relation', 'morph_one_relation_index');
            $table->integer('number')->default(0);
            $table->timestamps();
        });
    }
};