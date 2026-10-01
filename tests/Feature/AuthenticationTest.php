<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Token;

class AuthenticationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear test users
        User::where('email', 'like', '%test%')->delete();
    }

    public function test_user_can_register_and_receives_random_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name' => 'Test User',
            'email' => 'test_reg@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                ],
            ]);

        $token = $response->json('data.token');
        $this->assertEquals(64, strlen($token));

        // Verify token exists in MongoDB tokens collection
        $tokenInDb = Token::where('api_token', $token)->first();
        $this->assertNotNull($tokenInDb);
    }

    public function test_registration_fails_on_duplicate_email(): void
    {
        User::create([
            'name' => 'Existing',
            'email' => 'test_dup@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/register', [
            'name' => 'Another User',
            'email' => 'test_dup@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::create([
            'name' => 'Login User',
            'email' => 'test_login@example.com',
            'password' => bcrypt('secret123'),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test_login@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $token = $response->json('data.token');
        $this->assertEquals(64, strlen($token));
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpass',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_user_can_access_profile_with_valid_token(): void
    {
        $user = User::create([
            'name' => 'Profile User',
            'email' => 'test_profile@example.com',
            'password' => bcrypt('password123'),
        ]);

        $token = bin2hex(random_bytes(32));
        Token::create([
            'user_id' => (string)$user->_id,
            'api_token' => $token,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'test_profile@example.com')
            ->assertJsonMissing(['password']);
    }

    public function test_request_fails_with_invalid_or_missing_token(): void
    {
        // Missing token
        $response = $this->getJson('/api/me');
        $response->assertStatus(401);

        // Invalid token
        $response2 = $this->withHeader('Authorization', 'Bearer invalid_random_token')
            ->getJson('/api/me');
        $response2->assertStatus(401);
    }

    public function test_logout_invalidates_token(): void
    {
        $user = User::create([
            'name' => 'Logout User',
            'email' => 'test_logout@example.com',
            'password' => bcrypt('password123'),
        ]);

        $token = bin2hex(random_bytes(32));
        Token::create([
            'user_id' => (string)$user->_id,
            'api_token' => $token,
        ]);

        // Logout
        $logoutResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/logout');

        $logoutResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Subsequent call must fail with 401
        $profileResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/me');

        $profileResponse->assertStatus(401);
    }
}
