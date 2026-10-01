<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Token;
use Illuminate\Support\Facades\Auth;

class AuthenticateWithToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (!$header || !preg_match('/Bearer\s+(\S+)/', $header, $matches)) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication token is required.',
                'errors' => (object)[],
            ], 401);
        }

        $bearerToken = $matches[1];

        // Search tokens collection in MongoDB
        $tokenRecord = Token::where('api_token', $bearerToken)->first();

        if (!$tokenRecord) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired authentication token.',
                'errors' => (object)[],
            ], 401);
        }

        $user = $tokenRecord->user;

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authenticated user not found.',
                'errors' => (object)[],
            ], 401);
        }

        // Attach authenticated user to the request and Auth guard
        Auth::setUser($user);
        $request->setUserResolver(function () use ($user) {
            return $user;
        });

        // Store active token record on request for logout reference
        $request->attributes->set('auth_token_record', $tokenRecord);

        return $next($request);
    }
}
