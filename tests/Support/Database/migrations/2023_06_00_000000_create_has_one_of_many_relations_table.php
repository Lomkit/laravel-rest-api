<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
        Schema::create('has_one_of_many_relations', function (Blueprint $table) {
            $table->id();
            $table->integer('number')->default(0);
            $table->foreignIdFor(Model::class)->nullable()->constrained(null, 'id', 'has_one_of_many_relations_model_foreign');
            $table->timestamps();
        });
    }
};
