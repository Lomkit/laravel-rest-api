<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desk_reporter', function (Blueprint $table): void {
            $table->foreignId('desk_id')->constrained();
            $table->foreignId('reporter_id')->constrained();
            $table->string('role', 40);
            $table->string('pass_number', 12)->unique();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
        });
    }
};
