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
        Schema::create('generations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('column_id')->comment('Reference to column conversation');
            $table->uuid('user_message_id')->comment('Reference to user message that triggered this generation');
            $table->string('status')->default('pending')->comment('pending|streaming|completed|error|cancelled');
            $table->longText('partial_output')->nullable()->comment('Accumulated stream output, not confirmed until completed');
            $table->unsignedInteger('prompt_tokens')->nullable()->comment('Token count from OpenRouter');
            $table->unsignedInteger('completion_tokens')->nullable()->comment('Token count from OpenRouter');
            $table->string('error_code')->nullable()->comment('Error code if status=error');
            $table->text('error_message')->nullable()->comment('Error message if status=error');
            $table->boolean('retryable')->default(false)->comment('Whether this generation can be retried');
            $table->timestamp('started_at')->nullable()->comment('When generation started streaming');
            $table->timestamp('completed_at')->nullable()->comment('When generation completed/failed/cancelled');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('column_id')->references('id')->on('column_conversations')->cascadeOnDelete();
            $table->foreign('user_message_id')->references('id')->on('messages')->cascadeOnDelete();

            // Only one active generation (pending|streaming) per column
            $table->index(['column_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generations');
    }
};
