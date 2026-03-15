<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_it_returns_a_redirect_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/login');
    }
}
