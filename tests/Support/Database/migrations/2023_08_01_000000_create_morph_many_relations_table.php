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
        Schema::create('morph_many_relations', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('morph_many_relation', 'morph_many_relation_index');
            $table->integer('number')->default(0);
            $table->timestamps();
        });
    }
};
