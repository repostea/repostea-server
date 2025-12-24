<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Helpers\ErrorHelper;
use App\Http\Controllers\Controller;
use App\Models\KarmaEvent;
use App\Services\NotificationService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class KarmaEventController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    /**
     * Get all karma events.
     */
    public function index(Request $request): JsonResponse
    {
        $events = KarmaEvent::orderBy('start_at', 'desc')->get();

        return response()->json([
            'data' => $events,
        ]);
    }

    /**
     * Create a new karma event.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:tide,boost,surge,wave',
            'description' => 'required|string|max:500',
            'multiplier' => 'required|numeric|min:1|max:10',
            'start_at' => 'required|date|after:now',
            'end_at' => 'required|date|after:start_at',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('messages.validation.invalid_data'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $event = KarmaEvent::create($validator->validated());

        return response()->json([
            'message' => __('messages.karma.event_created'),
            'data' => $event,
        ], 201);
    }

    /**
     * Update a karma event.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $event = KarmaEvent::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|string|in:tide,boost,surge,wave',
            'description' => 'sometimes|string|max:500',
            'multiplier' => 'sometimes|numeric|min:1|max:10',
            'start_at' => 'sometimes|date',
            'end_at' => 'sometimes|date|after:start_at',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => __('messages.validation.invalid_data'),
                'errors' => $validator->errors(),
            ], 422);
        }

        $event->update($validator->validated());

        return response()->json([
            'message' => __('messages.karma.event_updated'),
            'data' => $event,
        ]);
    }

    /**
     * Delete a karma event.
     */
    public function destroy(int $id): JsonResponse
    {
        $event = KarmaEvent::findOrFail($id);
        $event->delete();

        return response()->json([
            'message' => __('messages.karma.event_deleted'),
        ]);
    }

    /**
     * Manually trigger notifications for an event.
     */
    public function notify(int $id): JsonResponse
    {
        $event = KarmaEvent::findOrFail($id);

        // Check if event hasn't started yet
        if ($event->start_at <= now()) {
            return response()->json([
                'message' => __('messages.karma.event_already_started'),
            ], 400);
        }

        try {
            $usersNotified = $this->notificationService->notifyUpcomingKarmaEvent($event);

            return response()->json([
                'message' => __('messages.karma.notifications_sent'),
                'users_notified' => $usersNotified,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => __('messages.karma.notifications_failed'),
                'error' => ErrorHelper::getSafeError($e),
            ], 500);
        }
    }
}
