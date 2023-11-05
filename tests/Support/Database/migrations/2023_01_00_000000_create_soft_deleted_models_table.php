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
        Schema::create('soft_deleted_models', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->bigInteger('number');
            $table->string('string')->nullable();
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\BelongsToRelation::class)->nullable()->constrained();
            $table->nullableMorphs('morph_to_relation', 'morph_to_relation_index');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
