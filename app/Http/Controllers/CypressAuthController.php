<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

final class CypressAuthController extends Controller
{
    /**
     * Verify the Cypress secret header for additional security.
     */
    private function verifySecret(Request $request): bool
    {
        $secret = config('app.cypress_secret');

        // If no secret configured, allow (backwards compatibility in local/testing)
        if (empty($secret)) {
            return true;
        }

        return $request->header('X-Cypress-Secret') === $secret;
    }

    public function login(Request $request): JsonResponse
    {
        if (! $this->verifySecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
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

    /**
     * Create a model instance via factory for testing.
     */
    public function factory(Request $request): JsonResponse
    {
        if (! $this->verifySecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $modelClass = $request->input('model');
        $attributes = $request->input('attributes', []);

        if (! $modelClass || ! class_exists($modelClass)) {
            return response()->json(['error' => 'Invalid model class'], 400);
        }

        try {
            $model = $modelClass::create($attributes);

            return response()->json($model);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Run an artisan command for testing.
     */
    public function artisan(Request $request): JsonResponse
    {
        if (! $this->verifySecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $command = $request->input('command');

        if (! $command) {
            return response()->json(['error' => 'Command is required'], 400);
        }

        try {
            $exitCode = Artisan::call($command);
            $output = Artisan::output();

            return response()->json([
                'exitCode' => $exitCode,
                'output' => $output,
            ]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a model instance for cleanup.
     */
    public function cleanup(Request $request): JsonResponse
    {
        if (! $this->verifySecret($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $modelClass = $request->input('model');
        $id = $request->input('id');

        if (! $modelClass || ! class_exists($modelClass)) {
            return response()->json(['error' => 'Invalid model class'], 400);
        }

        if (! $id) {
            return response()->json(['error' => 'ID is required'], 400);
        }

        try {
            $model = $modelClass::find($id);
            if ($model) {
                $model->delete();
            }

            return response()->json(['success' => true]);
        } catch (Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
