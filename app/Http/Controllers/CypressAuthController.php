<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CypressAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $userId = $request->input('id');

        if (! $userId) {
            return response()->json(['error' => 'User ID is required'], 400);
        }

        $user = User::find($userId);

        if (! $user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->tokens()->delete();
        $token = $user->createToken('cypress_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
}
