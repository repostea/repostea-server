<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use const PHP_INT_MAX;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use Illuminate\Http\Request;

final class InvitationController extends Controller
{
    /**
     * Get user's invitations.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $invitations = Invitation::where('created_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($invitation) => [
                'id' => $invitation->id,
                'code' => $invitation->code,
                'max_uses' => $invitation->max_uses,
                'current_uses' => $invitation->current_uses,
                'is_active' => $invitation->is_active,
                'expires_at' => $invitation->expires_at?->format('Y-m-d H:i:s'),
                'created_at' => $invitation->created_at->format('Y-m-d H:i:s'),
                'registration_url' => config('app.client_url') . '/auth/register?invitation=' . $invitation->code,
            ]);

        $limit = $user->getInvitationLimit();
        $remaining = $user->getRemainingInvitations();

        return response()->json([
            'invitations' => $invitations,
            'limit' => $limit === PHP_INT_MAX ? 'unlimited' : $limit,
            'used' => $invitations->count(),
            'remaining' => $remaining === PHP_INT_MAX ? 'unlimited' : $remaining,
            'can_create' => $user->canCreateInvitation(),
        ]);
    }

    /**
     * Create a new invitation.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Check if the user can create invitations
        $canCreate = $user->canCreateInvitation();

        if (! $canCreate['can']) {
            return response()->json([
                'message' => $canCreate['reason'],
                'error' => 'cannot_create_invitation',
            ], 403);
        }

        // Validate request (optional: allow configuring max_uses and expires_at)
        $validated = $request->validate([
            'max_uses' => 'sometimes|integer|min:1|max:10',
            'expires_in_days' => 'sometimes|integer|min:1|max:365',
        ]);

        $maxUses = $validated['max_uses'] ?? config('invitations.default_max_uses', 1);
        $expiresInDays = $validated['expires_in_days'] ?? config('invitations.default_expiration_days', 30);

        // Create the invitation
        $invitation = Invitation::create([
            'code' => Invitation::generateCode(),
            'created_by' => $user->id,
            'max_uses' => $maxUses,
            'expires_at' => now()->addDays($expiresInDays),
        ]);

        // Get updated user data
        $remaining = $user->getRemainingInvitations();

        return response()->json([
            'invitation' => [
                'id' => $invitation->id,
                'code' => $invitation->code,
                'max_uses' => $invitation->max_uses,
                'current_uses' => $invitation->current_uses,
                'is_active' => $invitation->is_active,
                'expires_at' => $invitation->expires_at?->format('Y-m-d H:i:s'),
                'created_at' => $invitation->created_at->format('Y-m-d H:i:s'),
                'registration_url' => config('app.client_url') . '/auth/register?invitation=' . $invitation->code,
            ],
            'remaining' => $remaining === PHP_INT_MAX ? 'unlimited' : $remaining,
            'message' => 'Invitation created successfully.',
        ], 201);
    }

    /**
     * Delete an invitation.
     */
    public function destroy(Request $request, Invitation $invitation)
    {
        $user = $request->user();

        // Check if the invitation belongs to the user
        if ($invitation->created_by !== $user->id) {
            return response()->json([
                'message' => 'You do not have permission to delete this invitation.',
            ], 403);
        }

        // Don't allow deleting used invitations
        if ($invitation->current_uses > 0) {
            return response()->json([
                'message' => 'Cannot delete an invitation that has been used.',
            ], 403);
        }

        $invitation->delete();

        return response()->json([
            'message' => 'Invitation deleted successfully.',
        ]);
    }
}
