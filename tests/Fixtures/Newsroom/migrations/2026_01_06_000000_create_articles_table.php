<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->string('title');
            $table->string('slug', 120)->unique();
            $table->char('locale', 2)->default('en');
            $table->text('body');
            $table->text('summary')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedSmallInteger('priority')->default(0);
            $table->integer('word_count');
            $table->integer('reading_minutes')->storedAs('word_count / 200');
            $table->decimal('rating', 3, 1)->nullable();
            $table->double('score')->default(0);
            $table->boolean('featured')->default(false);
            $table->date('published_on')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->timestamp('embargoed_until')->nullable();
            $table->time('deadline')->nullable();
            $table->year('edition_year')->nullable();
            $table->json('metadata')->nullable();
            $table->text('options')->nullable();
            $table->binary('thumbnail')->nullable();
            $table->string('editor_token')->nullable();
            $table->string('internal_token')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['locale', 'title']);
        });
    }
};
