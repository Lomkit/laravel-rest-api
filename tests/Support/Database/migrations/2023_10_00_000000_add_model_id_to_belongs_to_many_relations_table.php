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
        Schema::table('belongs_to_many_relations', function (Blueprint $table) {
            $table->foreignIdFor(\Lomkit\Rest\Tests\Support\Models\Model::class)->nullable()->constrained();
        });
    }
};
