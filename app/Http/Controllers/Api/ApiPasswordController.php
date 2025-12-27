<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class ApiPasswordController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'current_password' => ['required', 'current_password'],
                'password' => ['required', Password::defaults(), 'confirmed'],
                'password_confirmation' => ['required'],
            ]);

            $request->user()->update([
                'password' => Hash::make($validated['password']),
            ]);

            return response()->json([
                'message' => __('messages.passwords.updated'),
                'status' => 'success',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => __('messages.passwords.update_error'),
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.passwords.update_error'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }
}
