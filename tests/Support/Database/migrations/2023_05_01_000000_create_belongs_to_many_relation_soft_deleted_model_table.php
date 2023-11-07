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
        Schema::create('belongs_to_many_relation_soft_deleted_model', function (Blueprint $table) {
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation::class)->constrained(indexName: 'belongs_to_many_soft_id_foreign');
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\SoftDeletedModel::class)->constrained(indexName: 'soft_deleted_id_foreign');

            $table->integer('number')->default(0);

            $table->timestamps();

            $table->primary(['soft_deleted_model_id', 'belongs_to_many_relation_id']);
        });
    }
};
