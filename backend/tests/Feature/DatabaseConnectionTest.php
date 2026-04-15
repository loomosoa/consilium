<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_database_connection_is_working(): void
    {
        $this->assertTrue(DB::connection()->getPdo() !== null);
    }

    public function test_database_is_postgresql(): void
    {
        $this->assertEquals('pgsql', DB::connection()->getDriverName());
    }

    public function test_database_name_is_consilium_test(): void
    {
        $dbName = DB::connection()->getDatabaseName();
        $this->assertEquals('consilium_test', $dbName);
    }
}
