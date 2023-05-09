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
        Schema::create('belongs_to_many_relation_model', function (Blueprint $table) {
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation::class)->constrained();
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\Model::class)->constrained();

            $table->timestamps();

            $table->primary(['model_id', 'belongs_to_many_relation_id']);
        });
    }
};