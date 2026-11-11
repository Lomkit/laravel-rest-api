<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('member_project', function (Blueprint $table): void {
            $table->foreignId('project_id');
            $table->foreignId('member_id');
            $table->string('role')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->unique(['project_id', 'member_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_project');
        Schema::dropIfExists('members');
        Schema::dropIfExists('projects');
    }
};
