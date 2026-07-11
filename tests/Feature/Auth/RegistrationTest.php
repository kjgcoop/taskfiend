<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_register_endpoint_is_rate_limited_after_six_attempts(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->post('/register', [
                'name' => 'Test User',
                'email' => "test{$i}@example.com",
                'password' => 'password',
                'password_confirmation' => 'password',
            ]);

            $response->assertStatus(302);
            $response->assertRedirect(route('dashboard', absolute: false));

            $this->post('/logout');
        }

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test6@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertStatus(429);
    }
}
