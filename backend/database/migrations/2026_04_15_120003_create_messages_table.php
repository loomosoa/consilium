<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('column_id');
            $table->string('role');
            $table->text('content');
            $table->unsignedInteger('sequence');
            $table->uuid('generation_id')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('column_id')->references('id')->on('column_conversations')->cascadeOnDelete();
            $table->unique(['column_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
