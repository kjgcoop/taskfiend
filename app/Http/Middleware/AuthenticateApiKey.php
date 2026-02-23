<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'API key required. Provide as Bearer token.',
            ], 401);
        }

        // Use the stored key prefix to narrow candidates to (at most) a handful of
        // rows before doing the expensive Hash::check(). Keys created before the
        // prefix column was added fall back to the old full-scan path via the
        // whereNull('key_prefix') clause, so there is no breaking change.
        $prefix = strlen($token) >= 12 ? substr($token, 0, 12) : null;

        $apiKeys = ApiKey::whereNull('invalidated_at')
            ->where(function ($q) use ($prefix) {
                if ($prefix) {
                    $q->where('key_prefix', $prefix)
                      ->orWhereNull('key_prefix');
                }
            })
            ->with('user')
            ->get();

        $validKey = null;
        foreach ($apiKeys as $apiKey) {
            if (Hash::check($token, $apiKey->key_hash)) {
                $validKey = $apiKey;
                break;
            }
        }

        if (!$validKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired API key.',
            ], 401);
        }

        if (!$validKey->user->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => 'User account is disabled.',
            ], 403);
        }

        $request->setUserResolver(function () use ($validKey) {
            return $validKey->user;
        });

        return $next($request);
    }
}
