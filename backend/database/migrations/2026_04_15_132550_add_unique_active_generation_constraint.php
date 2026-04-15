<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Partial unique index: only one active generation (pending|streaming) per column
        DB::statement("
            CREATE UNIQUE INDEX unique_active_generation_per_column 
            ON generations (column_id) 
            WHERE status IN ('pending', 'streaming')
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS unique_active_generation_per_column');
    }
};
