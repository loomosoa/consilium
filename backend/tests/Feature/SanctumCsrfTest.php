<?php

namespace Tests\Feature;

use Tests\TestCase;

class SanctumCsrfTest extends TestCase
{
    public function test_sanctum_csrf_cookie_endpoint_is_accessible(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertStatus(204);
    }

    public function test_sanctum_csrf_cookie_is_set(): void
    {
        $response = $this->get('/sanctum/csrf-cookie');

        $response->assertCookie('XSRF-TOKEN');
    }
}
