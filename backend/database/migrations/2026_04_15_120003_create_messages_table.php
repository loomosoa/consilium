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
            $table->uuid('column_id')->comment('Reference to column conversation');
            $table->string('role')->comment('system|user|assistant');
            $table->text('content')->comment('Message content');
            $table->unsignedInteger('sequence')->comment('Sequential number within column');
            $table->uuid('generation_id')->nullable()->comment('Reference to generation that created this message');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('column_id')->references('id')->on('column_conversations')->cascadeOnDelete();
            $table->unique(['column_id', 'sequence']);
            $table->index('role');
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
