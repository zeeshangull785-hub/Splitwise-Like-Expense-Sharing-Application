<?php

namespace App\Services;

use App\Models\User;
use App\Models\Token;
use App\Exceptions\ApiException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthenticationService
{
    /**
     * Generate a cryptographically secure 64-char random hex token.
     */
    public function generateRandomToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Register a new user and generate an initial random API token.
     */
    public function register(array $data): array
    {
        $existing = User::where('email', strtolower(trim($data['email'])))->first();
        if ($existing) {
            Log::warning('Registration failed: Email already registered', ['email' => $data['email']]);
            throw new ApiException('Email is already registered.', 422, [
                'email' => ['The email has already been taken.']
            ]);
        }

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
        ]);

        $randomToken = $this->generateRandomToken();

        $tokenRecord = Token::create([
            'user_id' => (string)$user->_id,
            'api_token' => $randomToken,
        ]);

        Log::info('User successfully registered', ['user_id' => (string)$user->_id]);

        return [
            'user' => $user,
            'token' => $randomToken,
        ];
    }

    /**
     * Authenticate user with credentials and issue a new random API token.
     */
    public function login(array $credentials): array
    {
        $user = User::where('email', strtolower(trim($credentials['email'])))->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            Log::warning('Login failed: Invalid credentials', ['email' => $credentials['email']]);
            throw new ApiException('Invalid credentials provided.', 401);
        }

        $randomToken = $this->generateRandomToken();

        Token::create([
            'user_id' => (string)$user->_id,
            'api_token' => $randomToken,
        ]);

        Log::info('User successfully logged in', ['user_id' => (string)$user->_id]);

        return [
            'user' => $user,
            'token' => $randomToken,
        ];
    }

    /**
     * Invalidate the current session token.
     */
    public function logout(User $user, ?Token $tokenRecord = null): void
    {
        if ($tokenRecord) {
            $tokenRecord->delete();
        } else {
            // Delete all tokens for this user if no specific token record is present
            Token::where('user_id', (string)$user->_id)->delete();
        }

        Log::info('User logged out', ['user_id' => (string)$user->_id]);
    }
}
