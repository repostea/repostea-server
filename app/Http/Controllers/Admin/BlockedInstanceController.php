<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityPubBlockedInstance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class BlockedInstanceController extends Controller
{
    /**
     * List all blocked instances.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActivityPubBlockedInstance::query()
            ->with('blocker:id,username')
            ->orderBy('created_at', 'desc');

        // Filter by active status
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        // Filter by block type
        if ($request->has('block_type')) {
            $query->where('block_type', $request->get('block_type'));
        }

        // Search by domain
        if ($request->filled('search')) {
            $query->where('domain', 'like', '%' . $request->get('search') . '%');
        }

        $blockedInstances = $query->paginate($request->get('per_page', 25));

        return response()->json($blockedInstances);
    }

    /**
     * Block a new instance.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string|max:255',
            'reason' => 'nullable|string|max:500',
            'block_type' => 'sometimes|in:full,silence',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Normalize domain (remove protocol, paths, etc.)
        $domain = $this->normalizeDomain($request->get('domain'));

        if ($domain === null) {
            return response()->json([
                'message' => 'Invalid domain format',
            ], 422);
        }

        // Check if already blocked
        $existing = ActivityPubBlockedInstance::where('domain', $domain)->first();
        if ($existing !== null && $existing->is_active) {
            return response()->json([
                'message' => 'This instance is already blocked',
            ], 409);
        }

        $expiresAt = $request->filled('expires_at')
            ? \Carbon\Carbon::parse($request->get('expires_at'))
            : null;

        $blockedInstance = ActivityPubBlockedInstance::blockDomain(
            domain: $domain,
            reason: $request->get('reason'),
            blockType: $request->get('block_type', ActivityPubBlockedInstance::BLOCK_TYPE_FULL),
            blockedBy: $request->user()->id,
            expiresAt: $expiresAt,
        );

        return response()->json([
            'message' => 'Instance blocked successfully',
            'data' => $blockedInstance->load('blocker:id,username'),
        ], 201);
    }

    /**
     * Update a blocked instance.
     */
    public function update(Request $request, ActivityPubBlockedInstance $blockedInstance): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:500',
            'block_type' => 'sometimes|in:full,silence',
            'is_active' => 'sometimes|boolean',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $blockedInstance->update($request->only([
            'reason',
            'block_type',
            'is_active',
            'expires_at',
        ]));

        ActivityPubBlockedInstance::clearCache();

        return response()->json([
            'message' => 'Block updated successfully',
            'data' => $blockedInstance->fresh()->load('blocker:id,username'),
        ]);
    }

    /**
     * Unblock an instance.
     */
    public function destroy(ActivityPubBlockedInstance $blockedInstance): JsonResponse
    {
        $domain = $blockedInstance->domain;
        $blockedInstance->delete();

        ActivityPubBlockedInstance::clearCache();

        return response()->json([
            'message' => "Instance {$domain} has been unblocked",
        ]);
    }

    /**
     * Check if a domain is blocked.
     */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $domain = $this->normalizeDomain($request->get('domain'));

        if ($domain === null) {
            return response()->json([
                'message' => 'Invalid domain format',
            ], 422);
        }

        $status = ActivityPubBlockedInstance::getStatus($domain);

        return response()->json([
            'domain' => $domain,
            'status' => $status,
        ]);
    }

    /**
     * Normalize a domain (remove protocol, www, paths, etc.).
     */
    private function normalizeDomain(string $input): ?string
    {
        $input = trim($input);

        // If it looks like a URL, parse it
        if (str_contains($input, '://')) {
            $parsed = parse_url($input);

            return $parsed['host'] ?? null;
        }

        // If it starts with @, it's an actor handle
        if (str_starts_with($input, '@')) {
            $parts = explode('@', ltrim($input, '@'));

            return $parts[1] ?? $parts[0];
        }

        // Remove www. prefix
        if (str_starts_with($input, 'www.')) {
            $input = substr($input, 4);
        }

        // Validate it looks like a domain
        if (! preg_match('/^[a-z0-9][-a-z0-9]*(\.[a-z0-9][-a-z0-9]*)+$/i', $input)) {
            return null;
        }

        return strtolower($input);
    }
}
