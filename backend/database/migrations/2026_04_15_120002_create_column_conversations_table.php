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
        Schema::create('column_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workspace_id')->comment('Reference to workspace');
            $table->string('model_code')->comment('Model code: xai, google, zai, openai');
            $table->string('title')->nullable()->comment('Optional column title');
            $table->unsignedSmallInteger('position')->comment('Column position 1-4 within workspace');
            $table->string('status')->default('idle')->comment('idle|waiting|streaming|completed|error|cancelled');
            $table->uuid('last_generation_id')->nullable()->comment('Reference to most recent generation');
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();
            $table->unique(['workspace_id', 'position']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('column_conversations');
    }
};
