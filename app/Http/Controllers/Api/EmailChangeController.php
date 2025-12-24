<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChangeEmailRequest;
use App\Models\ModerationLog;
use App\Models\User;
use App\Notifications\EmailChangeConfirmation;
use App\Notifications\EmailChangeRequested;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class EmailChangeController extends Controller
{
    /**
     * Request an email change.
     * Validates password, stores pending email, and sends confirmation emails.
     */
    public function request(ChangeEmailRequest $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();
            $newEmail = $request->validated('email');

            // Check if the new email is the same as current
            if ($user->email === $newEmail) {
                return response()->json([
                    'message' => __('messages.email_change.same_email'),
                    'errors' => ['email' => [__('messages.email_change.same_email')]],
                ], 422);
            }

            // Generate a secure token
            $token = Str::random(64);

            // Store the pending email change
            $user->pending_email = $newEmail;
            $user->email_change_token = $token;
            $user->email_change_requested_at = now();
            $user->save();

            // Send notification to current email (warning about the change request)
            $user->notify(new EmailChangeRequested($newEmail));

            // Send confirmation link to new email
            $user->notify(new EmailChangeConfirmation($newEmail, $token));

            return response()->json([
                'message' => __('messages.email_change.verification_sent'),
                'status' => 'pending',
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => __('messages.email_change.request_error'),
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.email_change.request_error'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    /**
     * Confirm the email change using the token.
     */
    public function confirm(Request $request): JsonResponse
    {
        $validator = validator($request->all(), [
            'token' => ['required', 'string', 'size:64'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('messages.email_change.invalid_token'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $validated = $validator->validated();

        try {
            // Find user with this token
            $user = User::where('email_change_token', $validated['token'])->first();

            if ($user === null) {
                return response()->json([
                    'message' => __('messages.email_change.invalid_token'),
                    'status' => 'error',
                ], 400);
            }

            // Check if token is expired (24 hours)
            if ($user->isEmailChangeTokenExpired()) {
                $user->clearPendingEmailChange();

                return response()->json([
                    'message' => __('messages.email_change.token_expired'),
                    'status' => 'error',
                ], 400);
            }

            $oldEmail = $user->email;
            $newEmail = $user->pending_email;

            // Update the email
            $user->email = $newEmail;
            $user->email_verified_at = now(); // Mark as verified since they confirmed via email
            $user->pending_email = null;
            $user->email_change_token = null;
            $user->email_change_requested_at = null;
            $user->save();

            // Log the change for audit purposes
            ModerationLog::logAction(
                moderatorId: $user->id,
                action: 'email_changed',
                targetUserId: $user->id,
                targetType: User::class,
                targetId: $user->id,
                reason: 'User changed their email address',
                metadata: [
                    'old_email' => $oldEmail,
                    'new_email' => $newEmail,
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'verification_method' => 'password_and_email_confirmation',
                    'changed_at' => now()->toIso8601String(),
                ],
            );

            return response()->json([
                'message' => __('messages.email_change.success'),
                'status' => 'success',
                'email' => $newEmail,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.email_change.confirm_error'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    /**
     * Cancel a pending email change.
     */
    public function cancel(Request $request): JsonResponse
    {
        try {
            /** @var User $user */
            $user = $request->user();

            if (! $user->hasPendingEmailChange()) {
                return response()->json([
                    'message' => __('messages.email_change.no_pending_change'),
                    'status' => 'error',
                ], 400);
            }

            $user->clearPendingEmailChange();

            return response()->json([
                'message' => __('messages.email_change.cancelled'),
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.email_change.cancel_error'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }

    /**
     * Get the status of a pending email change.
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasPendingEmailChange()) {
            return response()->json([
                'has_pending_change' => false,
            ]);
        }

        return response()->json([
            'has_pending_change' => true,
            'pending_email' => $user->pending_email,
            'requested_at' => $user->email_change_requested_at?->toIso8601String(),
            'expires_at' => $user->email_change_requested_at?->addHours(24)->toIso8601String(),
            'is_expired' => $user->isEmailChangeTokenExpired(),
        ]);
    }
}
