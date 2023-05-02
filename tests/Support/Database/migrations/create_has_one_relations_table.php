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
        Schema::create('has_one_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\Model::class)->constrained();
            $table->timestamps();
        });
    }
};