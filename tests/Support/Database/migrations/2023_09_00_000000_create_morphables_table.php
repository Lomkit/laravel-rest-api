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
        Schema::create('morphables', function (Blueprint $table) {
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\MorphToManyRelation::class)->constrained(indexName: 'morph_to_many_relation_self_id_foreign');
            $table->morphs('morphable');
            $table->integer('number')->default(0);

            $table->timestamps();

            $table->primary(['morph_to_many_relation_id', 'morphable_id', 'morphable_type']);
        });
    }
};
