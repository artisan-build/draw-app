<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('creator_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->json('scene');
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestamps();

            $table->index(['creator_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boards');
    }
};
