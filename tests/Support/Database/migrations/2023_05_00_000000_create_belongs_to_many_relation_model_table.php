<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\Model;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('belongs_to_many_relation_model', function (Blueprint $table) {
            $table->foreignIdFor(BelongsToManyRelation::class)->constrained(indexName: 'belongs_to_many_relation_self_id_foreign');
            $table->foreignIdFor(Model::class)->constrained();

            $table->integer('number')->default(0)->nullable();

            $table->timestamps();

            $table->primary(['model_id', 'belongs_to_many_relation_id']);
        });
    }
};
