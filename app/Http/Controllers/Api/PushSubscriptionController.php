<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;

/**
 * API controller for push subscription management.
 * Notification preferences are in NotificationPreferencesController.
 * Core notification CRUD is in NotificationController.
 */
final class PushSubscriptionController extends Controller
{
    /**
     * Get VAPID public key for push subscription.
     */
    public function getVapidPublicKey(): JsonResponse
    {
        return response()->json([
            'public_key' => config('webpush.vapid.public_key'),
        ]);
    }

    /**
     * Save push subscription for the authenticated user.
     */
    public function savePushSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string|max:500',
            'keys.p256dh' => 'required|string',
            'keys.auth' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);

        $user = $request->user();

        // Update or create subscription
        $user->updatePushSubscription(
            $request->input('endpoint'),
            $request->input('keys.p256dh'),
            $request->input('keys.auth'),
        );

        // Update device info on the subscription
        $subscription = PushSubscription::where('endpoint', $request->input('endpoint'))->first();
        if ($subscription) {
            $subscription->update([
                'user_agent' => $request->userAgent(),
                'device_name' => $request->input('device_name'),
            ]);
        }

        return response()->json([
            'message' => __('notifications.push_subscription_saved'),
            'subscription_count' => $user->pushSubscriptions()->count(),
        ]);
    }

    /**
     * Remove push subscription for the authenticated user.
     */
    public function removePushSubscription(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = $request->user();
        $user->deletePushSubscription($request->input('endpoint'));

        return response()->json([
            'message' => __('notifications.push_subscription_removed'),
            'subscription_count' => $user->pushSubscriptions()->count(),
        ]);
    }

    /**
     * Get all push subscriptions for the authenticated user.
     */
    public function getPushSubscriptions(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptions = $user->pushSubscriptions()
            ->select('id', 'endpoint', 'user_agent', 'device_name', 'created_at')
            ->get()
            ->map(fn ($sub) => [
                'id' => $sub->id,
                'device_name' => $sub->device_name ?? $this->parseDeviceFromUserAgent($sub->user_agent),
                'created_at' => $sub->created_at->toISOString(),
            ]);

        return response()->json([
            'subscriptions' => $subscriptions,
        ]);
    }

    /**
     * Send a test push notification.
     */
    public function sendTestPush(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->pushSubscriptions()->count() === 0) {
            return response()->json([
                'message' => __('notifications.no_subscriptions'),
            ], 400);
        }

        $user->notify(new \App\Notifications\TestNotification());

        return response()->json([
            'message' => __('notifications.test_sent'),
        ]);
    }

    /**
     * Parse device name from user agent string.
     */
    private function parseDeviceFromUserAgent(?string $userAgent): string
    {
        if (! $userAgent) {
            return 'Unknown device';
        }

        // Simple detection for common browsers/platforms
        if (str_contains($userAgent, 'Chrome') && str_contains($userAgent, 'Mobile')) {
            return 'Chrome Mobile';
        }
        if (str_contains($userAgent, 'Firefox') && str_contains($userAgent, 'Mobile')) {
            return 'Firefox Mobile';
        }
        if (str_contains($userAgent, 'Safari') && str_contains($userAgent, 'Mobile')) {
            return 'Safari Mobile';
        }
        if (str_contains($userAgent, 'Chrome')) {
            return 'Chrome Desktop';
        }
        if (str_contains($userAgent, 'Firefox')) {
            return 'Firefox Desktop';
        }
        if (str_contains($userAgent, 'Safari')) {
            return 'Safari Desktop';
        }
        if (str_contains($userAgent, 'Edge')) {
            return 'Microsoft Edge';
        }

        return 'Web Browser';
    }
}
